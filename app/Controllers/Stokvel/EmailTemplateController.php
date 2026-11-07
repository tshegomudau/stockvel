<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\AuditService;
use App\Services\Notifications\EmailTemplateService;

final class EmailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        /** @var EmailTemplateService $templates */
        $templates = app()->container()->get(EmailTemplateService::class);
        $items = [];
        foreach ($templates->keys() as $key) {
            $items[$key] = $templates->resolve($sid, $key);
        }

        return $this->render('stokvel/settings/email-templates', [
            'title' => 'Email templates',
            'activeNav' => 'settings',
            'navContext' => 'app',
            'templates' => $items,
            'defaults' => EmailTemplateService::DEFAULTS,
        ]);
    }

    public function edit(Request $request, string $key): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        /** @var EmailTemplateService $templates */
        $templates = app()->container()->get(EmailTemplateService::class);
        try {
            $tpl = $templates->resolve($sid, $key);
        } catch (HttpException) {
            Session::flash('error', 'Unknown template.');
            return $this->redirect('/app/settings/email-templates');
        }

        return $this->render('stokvel/settings/email-template-edit', [
            'title' => 'Edit email template',
            'activeNav' => 'settings',
            'navContext' => 'app',
            'templateKey' => $key,
            'template' => $tpl,
            'defaults' => EmailTemplateService::DEFAULTS[$key],
        ]);
    }

    public function update(Request $request, string $key): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        try {
            app()->container()->get(EmailTemplateService::class)->save(
                $sid,
                $key,
                (string) $request->input('subject', ''),
                (string) $request->input('body', '')
            );
            app()->container()->get(AuditService::class)->log(
                'email_template.updated',
                'email_template',
                null,
                metadata: ['template_key' => $key],
                stokvelId: $sid
            );
            Session::flash('success', 'Template saved.');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/settings/email-templates/' . rawurlencode($key) . '/edit');
        }
        return $this->redirect('/app/settings/email-templates/' . rawurlencode($key) . '/edit');
    }

    public function reset(Request $request, string $key): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        app()->container()->get(EmailTemplateService::class)->reset($sid, $key);
        Session::flash('success', 'Template reset to default.');
        return $this->redirect('/app/settings/email-templates/' . rawurlencode($key) . '/edit');
    }
}
