<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Request\Request;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Findings\ExplanationRegistry;

class FindingController
{
    public function index(Request $request)
    {
        $query = Finding::orderBy('id')->desc();

        $severity = $this->csvParam($request, 'severity');
        if ($severity !== []) {
            $query = $query->whereIn('severity', $severity);
        }

        $status = $this->csvParam($request, 'status');
        if ($status !== []) {
            $query = $query->whereIn('status', $status);
        }

        if ($request->get('target_id')) {
            $query = $query->where('target_id', (int) $request->get('target_id'));
        }

        if ($request->get('change_type')) {
            $query = $query->where('change_type', sanitize_text_field((string) $request->get('change_type')));
        }

        if ($request->get('date_from')) {
            $query = $query->where('created_at', '>=', sanitize_text_field((string) $request->get('date_from')) . ' 00:00:00');
        }

        if ($request->get('date_to')) {
            $query = $query->where('created_at', '<=', sanitize_text_field((string) $request->get('date_to')) . ' 23:59:59');
        }

        $perPage = min(100, max(1, (int) ($request->get('per_page') ?: 20)));
        $page    = max(1, (int) ($request->get('page') ?: 1));

        $total    = $this->countFiltered($request);
        $findings = $query->take($perPage)->skip(($page - 1) * $perPage)->get() ?: [];

        return Response::success(
            [
                'items'    => array_map([$this, 'present'], \is_array($findings) ? $findings : [$findings]),
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
            ]
        );
    }

    public function show(Request $request)
    {
        $finding = Finding::findOne(['id' => (int) $request->get('id')]);
        if (!$finding) {
            return Response::error(__('Finding not found.', 'seo-change-monitor'));
        }

        return Response::success($this->present($finding));
    }

    public function resolve(Request $request)
    {
        return $this->close($request, Finding::STATUS_RESOLVED);
    }

    public function mute(Request $request)
    {
        return $this->close($request, Finding::STATUS_MUTED);
    }

    public function reopen(Request $request)
    {
        $finding = Finding::findOne(['id' => (int) $request->get('id')]);
        if (!$finding) {
            return Response::error(__('Finding not found.', 'seo-change-monitor'));
        }

        Db::update(
            'findings',
            ['status' => Finding::STATUS_OPEN, 'resolved_at' => null, 'resolved_by' => null],
            ['id' => $finding->id]
        );

        return Response::success($this->present(Finding::findOne(['id' => $finding->id])));
    }

    private function close(Request $request, $status)
    {
        $finding = Finding::findOne(['id' => (int) $request->get('id')]);
        if (!$finding) {
            return Response::error(__('Finding not found.', 'seo-change-monitor'));
        }

        $note = sanitize_textarea_field((string) $request->get('note'));
        if ($note === '') {
            return Response::error(__('Please add a short note explaining why.', 'seo-change-monitor'));
        }

        Db::update(
            'findings',
            [
                'status'      => $status,
                'note'        => $note,
                'resolved_at' => gmdate('Y-m-d H:i:s'),
                'resolved_by' => get_current_user_id() ?: null,
            ],
            ['id' => $finding->id]
        );

        return Response::success($this->present(Finding::findOne(['id' => $finding->id])));
    }

    /**
     * Shapes one finding for the frontend, always including the rendered
     * plain-language explanation.
     */
    private function present($finding)
    {
        $before = json_decode((string) $finding->before_value, true) ?: [];
        $after  = json_decode((string) $finding->after_value, true) ?: [];
        $target = $finding->target_id ? Target::findOne(['id' => $finding->target_id]) : null;

        return [
            'id'                => $finding->id,
            'target_id'         => $finding->target_id,
            'target_label'      => $target ? $target->label : null,
            'target_url'        => $target ? $target->url : null,
            'target_type'       => $target ? $target->type : null,
            'change_type'       => $finding->change_type,
            'severity'          => $finding->severity,
            'status'            => $finding->status,
            'before'            => isset($before['value']) ? $before['value'] : null,
            'after'             => isset($after['value']) ? $after['value'] : null,
            'context'           => isset($after['context']) ? $after['context'] : [],
            'attributed_events' => json_decode((string) $finding->attributed_events, true) ?: [],
            'is_expected'       => (bool) $finding->is_expected,
            'note'              => $finding->note,
            'resolved_at'       => $finding->resolved_at,
            'created_at'        => $finding->created_at,
            'explanation'       => ExplanationRegistry::get(
                $finding->change_type,
                isset($after['context']) && \is_array($after['context']) ? $after['context'] : []
            ),
        ];
    }

    private function countFiltered(Request $request)
    {
        $query = Finding::select(['id']);

        $severity = $this->csvParam($request, 'severity');
        if ($severity !== []) {
            $query = $query->whereIn('severity', $severity);
        }

        $status = $this->csvParam($request, 'status');
        if ($status !== []) {
            $query = $query->whereIn('status', $status);
        }

        if ($request->get('target_id')) {
            $query = $query->where('target_id', (int) $request->get('target_id'));
        }

        if ($request->get('change_type')) {
            $query = $query->where('change_type', sanitize_text_field((string) $request->get('change_type')));
        }

        if ($request->get('date_from')) {
            $query = $query->where('created_at', '>=', sanitize_text_field((string) $request->get('date_from')) . ' 00:00:00');
        }

        if ($request->get('date_to')) {
            $query = $query->where('created_at', '<=', sanitize_text_field((string) $request->get('date_to')) . ' 23:59:59');
        }

        return (int) $query->count();
    }

    private function csvParam(Request $request, $key)
    {
        $value = $request->get($key);

        if (\is_array($value)) {
            return array_filter(array_map('sanitize_text_field', $value));
        }

        if (\is_string($value) && $value !== '') {
            return array_filter(array_map('trim', explode(',', sanitize_text_field($value))));
        }

        return [];
    }
}
