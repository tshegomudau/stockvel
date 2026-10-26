<?php

declare(strict_types=1);

use App\Core\App;
use App\Exceptions\TenantException;
use App\Services\Meetings\MeetingService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Meeting resolution, vote, and action item workflow', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];

            $member = $db->fetch(
                "SELECT id, user_id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($member !== null, 'Active membership required');
            $mid = (int) $member['id'];
            $uid = (int) $member['user_id'];

            $now = now_utc();
            $ref = 'MTG-TEST-' . bin2hex(random_bytes(3));
            $meetingId = (int) $db->insert(
                'INSERT INTO meetings
                    (stokvel_id, meeting_ref, title, meeting_type, status, scheduled_at, venue,
                     notes, minutes_html, created_by_user_id, created_at, updated_at)
                 VALUES
                    (:sid, :ref, :title, :type, :status, :scheduled_at, :venue, :notes, :minutes, :by, :ca, :ua)',
                [
                    'sid' => $sid,
                    'ref' => $ref,
                    'title' => 'Workflow meeting ' . $ref,
                    'type' => 'regular',
                    'status' => 'scheduled',
                    'scheduled_at' => $now,
                    'venue' => 'Hall A',
                    'notes' => null,
                    'minutes' => '<p>Draft minutes for acknowledgement</p>',
                    'by' => $uid,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            test_assert($meetingId > 0, 'Meeting insert failed');

            /** @var MeetingService $meetings */
            $meetings = App::getInstance()->container()->get(MeetingService::class);

            $resolutionId = $meetings->addResolution(
                $sid,
                $meetingId,
                $uid,
                'Approve annual contribution increase',
                'Increase monthly contribution by R50 from next cycle.'
            );
            test_assert($resolutionId > 0, 'Resolution id expected');

            $listed = $meetings->listResolutions($sid, $meetingId);
            test_assert(count($listed) === 1, 'Expected one resolution');
            test_assert((string) $listed[0]['status'] === 'open', 'Resolution should be open');

            $meetings->castVote($sid, $resolutionId, $mid, 'yes');
            $vote = $meetings->voteForMembership($sid, $resolutionId, $mid);
            test_assert($vote === 'yes', 'Expected yes vote');

            $afterYes = $db->fetch('SELECT votes_for, votes_against, votes_abstain FROM resolutions WHERE id = :id', ['id' => $resolutionId]);
            test_assert((int) $afterYes['votes_for'] === 1, 'votes_for should be 1');
            test_assert((int) $afterYes['votes_against'] === 0, 'votes_against should be 0');

            // Changing vote updates counters (one vote per membership).
            $meetings->castVote($sid, $resolutionId, $mid, 'no');
            $afterNo = $db->fetch('SELECT votes_for, votes_against, votes_abstain FROM resolutions WHERE id = :id', ['id' => $resolutionId]);
            test_assert((int) $afterNo['votes_for'] === 0, 'votes_for should drop after change');
            test_assert((int) $afterNo['votes_against'] === 1, 'votes_against should be 1');

            $voteRows = $db->fetchAll(
                'SELECT id FROM votes WHERE stokvel_id = :sid AND resolution_id = :rid AND membership_id = :mid',
                ['sid' => $sid, 'rid' => $resolutionId, 'mid' => $mid]
            );
            test_assert(count($voteRows) === 1, 'Must keep a single vote row per membership');

            $actionId = $meetings->addActionItem($sid, $meetingId, $uid, [
                'title' => 'Circulate updated contribution notice',
                'description' => 'Send WhatsApp and email to all members',
                'assigned_membership_id' => $mid,
                'due_date' => gmdate('Y-m-d', time() + 86400 * 7),
            ]);
            test_assert($actionId > 0, 'Action item id expected');
            $actions = $meetings->listActionItems($sid, $meetingId);
            test_assert(count($actions) === 1, 'Expected one action item');
            test_assert((int) $actions[0]['assigned_to_membership_id'] === $mid, 'Assignee mismatch');

            $meetings->acknowledgeMinutes($sid, $meetingId, $mid);
            $ack = $db->fetch(
                'SELECT id FROM meeting_acknowledgements WHERE stokvel_id = :sid AND meeting_id = :mid AND user_id = :uid',
                ['sid' => $sid, 'mid' => $meetingId, 'uid' => $uid]
            );
            test_assert($ack !== null, 'Acknowledgement missing');

            // Idempotent acknowledge.
            $meetings->acknowledgeMinutes($sid, $meetingId, $mid);
            $ackCount = $db->fetch(
                'SELECT COUNT(*) AS c FROM meeting_acknowledgements WHERE stokvel_id = :sid AND meeting_id = :mid AND user_id = :uid',
                ['sid' => $sid, 'mid' => $meetingId, 'uid' => $uid]
            );
            test_assert((int) $ackCount['c'] === 1, 'Acknowledge must not duplicate');

            $threw = false;
            try {
                $meetings->castVote($sid, $resolutionId, $mid, 'maybe');
            } catch (TenantException) {
                $threw = true;
            }
            test_assert($threw, 'Invalid vote must throw TenantException');
        });
    });
};
