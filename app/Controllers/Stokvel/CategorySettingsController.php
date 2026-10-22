<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class CategorySettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $this->requireStokvel();

        $hubs = [
            [
                'title' => 'Expense categories',
                'description' => 'Organise spending for budgets and reports.',
                'url' => url('/app/expenses/categories'),
                'permission' => 'expenses.manage',
                'module' => 'expenses',
            ],
            [
                'title' => 'Payout categories',
                'description' => 'Classify payout cycles (burial, grocery, emergency).',
                'url' => url('/app/payouts/categories'),
                'permission' => 'payouts.manage',
                'module' => 'payouts',
            ],
            [
                'title' => 'Document categories',
                'description' => 'Catalogue document types members can upload.',
                'url' => url('/app/documents/categories'),
                'permission' => 'documents.manage_categories',
                'module' => 'documents',
            ],
            [
                'title' => 'Fee categories',
                'description' => 'Joining fees, levies, penalties, and other charges.',
                'url' => url('/app/fees/categories'),
                'permission' => 'fees.manage',
                'module' => null,
            ],
            [
                'title' => 'Meeting categories',
                'description' => 'Regular, AGM, special, and committee meeting types.',
                'url' => url('/app/meetings/categories'),
                'permission' => 'meetings.manage',
                'module' => 'meetings',
            ],
            [
                'title' => 'Rule categories',
                'description' => 'Group constitution and policy rules by topic.',
                'url' => url('/app/rules/categories'),
                'permission' => 'rules.manage',
                'module' => 'rules',
            ],
        ];

        $links = [];
        foreach ($hubs as $hub) {
            if (!empty($hub['module']) && !module_enabled((string) $hub['module'])) {
                continue;
            }
            if (!empty($hub['permission']) && !can((string) $hub['permission'])) {
                continue;
            }
            $links[] = $hub;
        }

        return $this->render('stokvel/settings/categories', [
            'title' => 'Category catalogues',
            'activeNav' => 'categories',
            'navContext' => 'app',
            'links' => $links,
        ]);
    }
}
