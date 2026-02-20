<?php

if (!defined('ABSPATH')) {
    exit;
}

final class FST_Github_Updater {
    private $plugin_file;
    private $plugin_basename;
    private $plugin_slug;
    private $current_version;
    private $repo_owner;
    private $repo_name;
    private $repo_api;
    private $repo_html;

    public function __construct($plugin_file, $current_version, $repo_owner, $repo_name) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->current_version = (string) $current_version;
        $this->repo_owner = (string) $repo_owner;
        $this->repo_name = (string) $repo_name;
        $this->repo_api = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name;
        $this->repo_html = 'https://github.com/' . $this->repo_owner . '/' . $this->repo_name;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked) || !is_object($transient)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release || empty($release['version'])) {
            return $transient;
        }

        if (version_compare($release['version'], $this->current_version, '<=')) {
            return $transient;
        }

        $package_url = isset($release['zipball_url']) ? (string) $release['zipball_url'] : '';
        if ($package_url === '') {
            return $transient;
        }

        $transient->response[$this->plugin_basename] = (object) array(
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_basename,
            'new_version' => $release['version'],
            'url' => $this->repo_html,
            'package' => $package_url,
            'tested' => get_bloginfo('version'),
            'requires_php' => PHP_VERSION,
            'icons' => array(),
            'banners' => array(),
            'banners_rtl' => array()
        );

        return $transient;
    }

    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $res;
        }

        $release = $this->get_latest_release();
        if (!$release || empty($release['version'])) {
            return $res;
        }

        return (object) array(
            'name' => 'Flight Schedule Timetable',
            'slug' => $this->plugin_slug,
            'version' => $release['version'],
            'author' => '<a href="' . esc_url($this->repo_html) . '">khliffz</a>',
            'author_profile' => $this->repo_html,
            'homepage' => $this->repo_html,
            'requires' => '5.8',
            'requires_php' => '7.4',
            'tested' => get_bloginfo('version'),
            'last_updated' => !empty($release['published_at']) ? gmdate('Y-m-d', strtotime($release['published_at'])) : '',
            'download_link' => !empty($release['zipball_url']) ? $release['zipball_url'] : '',
            'sections' => array(
                'description' => 'Flight schedule timetable plugin with API integration and analytics dashboard.',
                'changelog' => !empty($release['body']) ? wp_kses_post(wpautop($release['body'])) : 'No changelog provided.'
            )
        );
    }

    private function get_latest_release() {
        $cache_key = 'fst_github_latest_release';
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached['version'])) {
            return $cached;
        }

        $request = wp_remote_get($this->repo_api . '/releases/latest', array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/')
            )
        ));

        if (is_wp_error($request)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($request);
        $body = (string) wp_remote_retrieve_body($request);
        if ($status < 200 || $status >= 300 || $body === '') {
            return null;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }

        $tag = isset($json['tag_name']) ? (string) $json['tag_name'] : '';
        $version = ltrim($tag, 'vV');
        if ($version === '') {
            return null;
        }

        $data = array(
            'version' => $version,
            'tag_name' => $tag,
            'zipball_url' => isset($json['zipball_url']) ? (string) $json['zipball_url'] : '',
            'body' => isset($json['body']) ? (string) $json['body'] : '',
            'published_at' => isset($json['published_at']) ? (string) $json['published_at'] : ''
        );

        set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);

        return $data;
    }
}
