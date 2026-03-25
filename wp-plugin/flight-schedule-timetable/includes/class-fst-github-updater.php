<?php

if (!defined('ABSPATH')) {
    exit;
}

final class FST_GitHub_Updater {
    private $plugin_file;
    private $plugin_basename;
    private $repo;
    private $version;
    private $asset_name;
    private $cache_key;

    public function __construct($plugin_file, $repo, $version, $asset_name) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->repo = trim((string) $repo);
        $this->version = trim((string) $version);
        $this->asset_name = trim((string) $asset_name);
        $this->cache_key = 'fst_github_release_' . md5($this->repo);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'inject_plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    public function inject_update($transient) {
        if (!is_object($transient) || empty($transient->checked[$this->plugin_basename])) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release || empty($release['version']) || empty($release['package'])) {
            return $transient;
        }

        if (version_compare($release['version'], $this->version, '<=')) {
            return $transient;
        }

        $transient->response[$this->plugin_basename] = (object) [
            'slug' => dirname($this->plugin_basename),
            'plugin' => $this->plugin_basename,
            'new_version' => $release['version'],
            'package' => $release['package'],
            'url' => $release['url'],
            'tested' => '',
            'requires_php' => '',
        ];

        return $transient;
    }

    public function inject_plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args)) {
            return $result;
        }

        if (($args->slug ?? '') !== dirname($this->plugin_basename)) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'Flight Schedule Timetable',
            'slug' => dirname($this->plugin_basename),
            'version' => $release['version'] ?: $this->version,
            'author' => 'khliffz',
            'homepage' => $release['url'],
            'download_link' => $release['package'],
            'sections' => [
                'description' => 'Embed and track the Flight Schedule widget with a custom admin dashboard.',
                'changelog' => !empty($release['body']) ? wp_kses_post(wpautop($release['body'])) : 'No release notes provided.',
            ],
            'banners' => [],
            'requires' => '6.0',
            'requires_php' => '7.4',
        ];
    }

    public function after_install($response, $hook_extra, $result) {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $response;
        }

        if (empty($result['destination']) || empty($result['local_destination'])) {
            return $response;
        }

        global $wp_filesystem;

        $proper_destination = trailingslashit($result['local_destination']) . dirname($this->plugin_basename);
        if ($result['destination'] !== $proper_destination && !empty($wp_filesystem)) {
            $wp_filesystem->move($result['destination'], $proper_destination, true);
            $result['destination'] = $proper_destination;
        }

        if (!empty($hook_extra['plugin'])) {
            activate_plugin($hook_extra['plugin']);
        }

        return $response;
    }

    private function get_latest_release() {
        $cached = get_site_transient($this->cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/releases/latest', $this->repo),
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        $package = '';
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                if (($asset['name'] ?? '') === $this->asset_name) {
                    $package = (string) ($asset['browser_download_url'] ?? '');
                    break;
                }
            }
        }

        $release = [
            'version' => ltrim((string) ($data['tag_name'] ?? ''), 'v'),
            'package' => $package,
            'url' => (string) ($data['html_url'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
        ];

        set_site_transient($this->cache_key, $release, HOUR_IN_SECONDS);

        return $release;
    }
}
