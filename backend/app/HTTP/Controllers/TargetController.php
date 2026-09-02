<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Request\Request;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Db;

class TargetController
{
    public function index()
    {
        $targets = Target::where('type', Target::TYPE_PAGE)->orderBy('id')->desc()->get();

        return Response::success($targets ?: []);
    }

    public function store(Request $request)
    {
        $url    = $this->normalizeUrl((string) $request->get('url'));
        $postId = (int) $request->get('post_id') ?: null;

        if ($postId && !$url) {
            $permalink = get_permalink($postId);
            $url       = $permalink ? $this->normalizeUrl($permalink) : null;
        }

        if (!$url) {
            return Response::error(__('Please provide a valid URL on this site.', 'seo-change-monitor'));
        }

        if (Target::findOne(['url' => $url, 'type' => Target::TYPE_PAGE])) {
            return Response::error(__('This page is already being monitored.', 'seo-change-monitor'));
        }

        $label  = sanitize_text_field((string) $request->get('label'));
        $target = Target::insert(
            [
                'type'      => Target::TYPE_PAGE,
                'post_id'   => $postId,
                'url'       => $url,
                'label'     => $label !== '' ? $label : $url,
                'is_active' => 1,
            ]
        );

        return Response::success($target);
    }

    public function update(Request $request)
    {
        $target = $this->findPageTarget((int) $request->get('id'));
        if (!$target) {
            return Response::error(__('Monitored page not found.', 'seo-change-monitor'));
        }

        $updates = [];
        if ($request->has('label')) {
            $updates['label'] = sanitize_text_field((string) $request->get('label'));
        }

        if ($request->has('is_active')) {
            $updates['is_active'] = $request->get('is_active') ? 1 : 0;
        }

        if ($updates !== []) {
            Db::update('targets', $updates, ['id' => $target->id]);
        }

        return Response::success(Target::findOne(['id' => $target->id]));
    }

    public function destroy(Request $request)
    {
        $target = $this->findPageTarget((int) $request->get('id'));
        if (!$target) {
            return Response::error(__('Monitored page not found.', 'seo-change-monitor'));
        }

        Target::destroy([$target->id]);

        return Response::success(['deleted' => $target->id]);
    }

    public function searchPosts(Request $request)
    {
        $term = sanitize_text_field((string) $request->get('term'));

        $posts = get_posts(
            [
                'post_type'   => ['page', 'post'],
                'post_status' => 'publish',
                'numberposts' => 20,
                's'           => $term,
            ]
        );

        $results = [];
        foreach ($posts as $post) {
            $results[] = [
                'post_id' => $post->ID,
                'title'   => $post->post_title !== '' ? $post->post_title : __('(no title)', 'seo-change-monitor'),
                'url'     => get_permalink($post),
                'type'    => $post->post_type,
            ];
        }

        return Response::success($results);
    }

    private function findPageTarget($id)
    {
        if (!$id) {
            return false;
        }

        $target = Target::findOne(['id' => $id]);

        // System targets (robots/sitemap/site settings) are not user-editable.
        return $target && $target->type === Target::TYPE_PAGE ? $target : false;
    }

    /**
     * Only URLs on this site are monitorable; anything else returns null.
     */
    private function normalizeUrl($url)
    {
        $url = esc_url_raw(trim($url));
        if ($url === '') {
            return null;
        }

        $homeHost = wp_parse_url(home_url(), PHP_URL_HOST);
        $urlHost  = wp_parse_url($url, PHP_URL_HOST);

        if ($urlHost === null || strcasecmp((string) $urlHost, (string) $homeHost) !== 0) {
            return null;
        }

        return $url;
    }
}
