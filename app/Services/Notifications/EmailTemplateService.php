<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Support\HtmlSanitizer;

/**
 * Stokvel-scoped email templates with safe {{variable}} substitution.
 */
final class EmailTemplateService
{
    /** @var array<string, array{label:string,subject:string,body:string,variables:list<string>}> */
    public const DEFAULTS = [
        'invitation' => [
            'label' => 'Member invitation',
            'subject' => 'You are invited to join {{stokvel_name}}',
            'body' => "Hello {{member_name}},\n\nYou have been invited to join {{stokvel_name}}.\n\nAccept your invitation:\n{{invite_url}}\n\nThis invitation expires on {{expires_at}}.\n\nIf you did not expect this email, you can ignore it.",
            'variables' => ['stokvel_name', 'member_name', 'invite_url', 'inviter_name', 'expires_at'],
        ],
        'membership_approved' => [
            'label' => 'Membership approved',
            'subject' => 'Your membership of {{stokvel_name}} is approved',
            'body' => "Hello {{member_name}},\n\nYour membership of {{stokvel_name}} has been approved.\n\nMember number: {{member_number}}\n\nYou can sign in and open the member portal:\n{{portal_url}}\n",
            'variables' => ['stokvel_name', 'member_name', 'member_number', 'portal_url'],
        ],
        'announcement' => [
            'label' => 'Announcement email',
            'subject' => '{{stokvel_name}}: {{announcement_title}}',
            'body' => "{{announcement_title}}\n\n{{announcement_body}}\n\n— {{stokvel_name}}",
            'variables' => ['stokvel_name', 'announcement_title', 'announcement_body'],
        ],
        'meeting_reminder' => [
            'label' => 'Meeting reminder',
            'subject' => '{{stokvel_name}} meeting reminder',
            'body' => "Reminder: {{meeting_title}} for {{stokvel_name}}.\n\nWhen: {{meeting_when}}\n\n{{meeting_notes}}",
            'variables' => ['stokvel_name', 'meeting_title', 'meeting_when', 'meeting_notes'],
        ],
        'contribution_reminder' => [
            'label' => 'Contribution reminder',
            'subject' => '{{stokvel_name}} contribution reminder',
            'body' => "Hello {{member_name}},\n\nThis is a reminder from {{stokvel_name}} about your contribution.\n\nAmount due: {{amount_due}}\nDue date: {{due_date}}",
            'variables' => ['stokvel_name', 'member_name', 'amount_due', 'due_date'],
        ],
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /** @return array{subject:string,body:string,is_custom:bool,variables:list<string>,label:string} */
    public function resolve(int $stokvelId, string $key): array
    {
        $defaults = self::DEFAULTS[$key] ?? null;
        if ($defaults === null) {
            throw new HttpException(404, 'Unknown email template.');
        }

        $custom = null;
        try {
            $custom = $this->db->fetch(
                'SELECT subject, body_html, body_text FROM stokvel_email_templates
                 WHERE stokvel_id = :sid AND template_key = :k LIMIT 1',
                ['sid' => $stokvelId, 'k' => $key]
            );
        } catch (\Throwable) {
            $custom = null;
        }

        $stokvelName = (string) ($this->db->fetch(
            'SELECT name FROM stokvels WHERE id = :id LIMIT 1',
            ['id' => $stokvelId]
        )['name'] ?? 'your stokvel');

        if ($custom) {
            return [
                'subject' => (string) $custom['subject'],
                'body' => (string) ($custom['body_text'] ?? strip_tags((string) $custom['body_html'])),
                'body_html' => (string) $custom['body_html'],
                'is_custom' => true,
                'variables' => $defaults['variables'],
                'label' => $defaults['label'],
                'stokvel_name' => $stokvelName,
            ];
        }

        return [
            'subject' => $defaults['subject'],
            'body' => $defaults['body'],
            'body_html' => nl2br(e($defaults['body']), false),
            'is_custom' => false,
            'variables' => $defaults['variables'],
            'label' => $defaults['label'],
            'stokvel_name' => $stokvelName,
        ];
    }

    /**
     * @param array<string, string> $vars
     * @return array{subject:string,body:string}
     */
    public function render(int $stokvelId, string $key, array $vars): array
    {
        $tpl = $this->resolve($stokvelId, $key);
        if (!isset($vars['stokvel_name']) || trim($vars['stokvel_name']) === '') {
            $vars['stokvel_name'] = (string) ($tpl['stokvel_name'] ?? 'your stokvel');
        }
        $allowed = $tpl['variables'];
        $safeVars = [];
        foreach ($allowed as $name) {
            $safeVars[$name] = (string) ($vars[$name] ?? '');
        }
        // Always allow stokvel_name.
        $safeVars['stokvel_name'] = (string) ($vars['stokvel_name'] ?? $safeVars['stokvel_name'] ?? '');

        return [
            'subject' => $this->substitute((string) $tpl['subject'], $safeVars),
            'body' => $this->substitute((string) $tpl['body'], $safeVars),
        ];
    }

    public function save(int $stokvelId, string $key, string $subject, string $body): void
    {
        if (!isset(self::DEFAULTS[$key])) {
            throw new HttpException(404, 'Unknown email template.');
        }
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || $body === '') {
            throw new HttpException(422, 'Subject and body are required.');
        }
        $this->assertSafeTemplate($subject);
        $this->assertSafeTemplate($body);

        $allowed = self::DEFAULTS[$key]['variables'];
        $this->assertOnlyAllowedVariables($subject . "\n" . $body, $allowed);

        $safeHtml = HtmlSanitizer::clean(nl2br(e($body), false));
        $now = now_utc();
        $existing = $this->db->fetch(
            'SELECT id FROM stokvel_email_templates WHERE stokvel_id = :sid AND template_key = :k LIMIT 1',
            ['sid' => $stokvelId, 'k' => $key]
        );
        if ($existing) {
            $this->db->exec(
                'UPDATE stokvel_email_templates SET subject = :s, body_html = :h, body_text = :t, updated_at = :ua WHERE id = :id',
                ['s' => $subject, 'h' => $safeHtml, 't' => $body, 'ua' => $now, 'id' => (int) $existing['id']]
            );
            return;
        }
        $this->db->insert(
            'INSERT INTO stokvel_email_templates (stokvel_id, template_key, subject, body_html, body_text, created_at, updated_at)
             VALUES (:sid, :k, :s, :h, :t, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'k' => $key,
                's' => $subject,
                'h' => $safeHtml,
                't' => $body,
                'ca' => $now,
                'ua' => $now,
            ]
        );
    }

    public function reset(int $stokvelId, string $key): void
    {
        $this->db->exec(
            'DELETE FROM stokvel_email_templates WHERE stokvel_id = :sid AND template_key = :k',
            ['sid' => $stokvelId, 'k' => $key]
        );
    }

    /** @param array<string, string> $vars */
    private function substitute(string $template, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function (array $m) use ($vars): string {
                $key = strtolower($m[1]);
                return $vars[$key] ?? '';
            },
            $template
        );
    }

    private function assertSafeTemplate(string $value): void
    {
        if (preg_match('/<\s*script|javascript:|on\w+\s*=|<\?php|\{%/i', $value)) {
            throw new HttpException(422, 'Template contains unsafe content.');
        }
    }

    /** @param list<string> $allowed */
    private function assertOnlyAllowedVariables(string $value, array $allowed): void
    {
        if (!preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $value, $matches)) {
            return;
        }
        $allowed = array_map('strtolower', $allowed);
        if (!in_array('stokvel_name', $allowed, true)) {
            $allowed[] = 'stokvel_name';
        }
        foreach ($matches[1] as $var) {
            if (!in_array(strtolower($var), $allowed, true)) {
                throw new HttpException(422, 'Unknown template variable: {{' . $var . '}}');
            }
        }
    }
}
