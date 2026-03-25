<?php
/**
 * Plugin Name: Flight Schedule Timetable
 * Description: Embed and track the Flight Schedule widget with a custom admin dashboard (KPI, Analytics, Settings, Instructions).
 * Version: 1.1.0
 * Author: khliffz
 * Update URI: https://github.com/jkhliffz09/flight-schedule-timetable
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-fst-github-updater.php';

final class FST_Flight_Schedule_Timetable {
    const VERSION = '1.1.0';
    const SETTINGS_OPTION = 'fst_settings';
    const STATS_OPTION = 'fst_stats';
    const RECENT_SEARCH_LIMIT = 100;
    const ROUTE_COUNT_LIMIT = 200;

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);

        new FST_GitHub_Updater(
            __FILE__,
            'jkhliffz09/flight-schedule-timetable',
            self::VERSION,
            'flight-schedule-timetable-plugin.zip'
        );

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_shortcode('flight_schedule_widget', [$this, 'render_shortcode']);

        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_filter('rest_pre_serve_request', [$this, 'serve_raw_xml_response'], 10, 4);
    }

    public function activate() {
        $defaults = $this->default_settings();
        $settings = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        update_option(self::SETTINGS_OPTION, wp_parse_args($settings, $defaults));

        $stats = get_option(self::STATS_OPTION, []);
        if (!is_array($stats) || empty($stats)) {
            update_option(self::STATS_OPTION, [
                'totals' => [
                    'views' => 0,
                    'shortcode_renders' => 0,
                    'search_requests' => 0,
                ],
                'by_day' => [],
                'recent_searches' => [],
                'route_counts' => [],
            ]);
        }
    }

    private function default_settings() {
        return [
            'embed_js_url' => 'https://cdn.passrider.com/embed.js',
            'api_url' => 'https://services.flightlookup.com/v1/xml/TimeTable/',
            'subscription_key' => '',
            'result_limit' => '100',
            'default_height' => '860',
            'use_local_proxy' => '1',
        ];
    }

    private function get_settings() {
        $settings = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        return wp_parse_args($settings, $this->default_settings());
    }

    private function get_stats() {
        $stats = get_option(self::STATS_OPTION, []);
        if (!is_array($stats)) {
            $stats = [];
        }
        $stats = wp_parse_args($stats, [
            'totals' => [
                'views' => 0,
                'shortcode_renders' => 0,
                'search_requests' => 0,
            ],
            'by_day' => [],
            'recent_searches' => [],
            'route_counts' => [],
        ]);

        if (!isset($stats['totals']['views'])) {
            $stats['totals']['views'] = 0;
        }
        if (!isset($stats['totals']['shortcode_renders'])) {
            $stats['totals']['shortcode_renders'] = 0;
        }
        if (!isset($stats['totals']['search_requests'])) {
            $stats['totals']['search_requests'] = 0;
        }
        if (!is_array($stats['by_day'])) {
            $stats['by_day'] = [];
        }
        if (!is_array($stats['recent_searches'])) {
            $stats['recent_searches'] = [];
        }
        if (!is_array($stats['route_counts'])) {
            $stats['route_counts'] = [];
        }

        return $stats;
    }

    private function increment_stat($event) {
        $event = sanitize_key($event);
        if (!in_array($event, ['views', 'shortcode_renders', 'search_requests'], true)) {
            return;
        }

        $stats = $this->get_stats();
        $day = gmdate('Y-m-d');

        $stats['totals'][$event] = (int) $stats['totals'][$event] + 1;

        if (!isset($stats['by_day'][$day]) || !is_array($stats['by_day'][$day])) {
            $stats['by_day'][$day] = [
                'views' => 0,
                'shortcode_renders' => 0,
                'search_requests' => 0,
            ];
        }
        if (!isset($stats['by_day'][$day]['search_requests'])) {
            $stats['by_day'][$day]['search_requests'] = 0;
        }

        $stats['by_day'][$day][$event] = (int) $stats['by_day'][$day][$event] + 1;

        update_option(self::STATS_OPTION, $stats, false);
    }

    private function normalize_yn($value, $default = 'N') {
        $v = strtoupper(sanitize_text_field((string) $value));
        if ($v === 'Y' || $v === 'N') {
            return $v;
        }
        return $default;
    }

    private function record_search_analytics(array $payload) {
        $stats = $this->get_stats();
        $day = gmdate('Y-m-d');

        $from = strtoupper(sanitize_text_field((string) ($payload['from'] ?? '')));
        $to = strtoupper(sanitize_text_field((string) ($payload['to'] ?? '')));
        $date = preg_replace('/[^0-9]/', '', (string) ($payload['date'] ?? ''));
        $specificDate = $this->normalize_yn($payload['specificDate'] ?? 'Y', 'Y');
        $sevenDay = ($specificDate === 'Y') ? 'N' : 'Y';

        $entry = [
            'time_utc' => gmdate('c'),
            'from' => $from,
            'to' => $to,
            'date' => $date,
            'specificDate' => $specificDate,
            'sevenDay' => $sevenDay,
            'connection' => sanitize_text_field((string) ($payload['connection'] ?? 'AUTO')),
            'sort' => sanitize_text_field((string) ($payload['sort'] ?? 'Departure')),
            'time' => sanitize_text_field((string) ($payload['time'] ?? 'ANY')),
            'airline' => sanitize_text_field((string) ($payload['airline'] ?? '---')),
            'result' => (string) max(1, min(500, (int) ($payload['result'] ?? 100))),
            'codeshare' => $this->normalize_yn($payload['codeshare'] ?? 'N', 'N'),
            'interline' => $this->normalize_yn($payload['interline'] ?? 'N', 'N'),
            'language' => sanitize_text_field((string) ($payload['language'] ?? 'en')),
            'compression' => sanitize_text_field((string) ($payload['compression'] ?? 'MOST')),
        ];

        $stats['totals']['search_requests'] = (int) ($stats['totals']['search_requests'] ?? 0) + 1;

        if (!isset($stats['by_day'][$day]) || !is_array($stats['by_day'][$day])) {
            $stats['by_day'][$day] = [
                'views' => 0,
                'shortcode_renders' => 0,
                'search_requests' => 0,
            ];
        }
        if (!isset($stats['by_day'][$day]['search_requests'])) {
            $stats['by_day'][$day]['search_requests'] = 0;
        }
        $stats['by_day'][$day]['search_requests'] = (int) $stats['by_day'][$day]['search_requests'] + 1;

        array_unshift($stats['recent_searches'], $entry);
        $stats['recent_searches'] = array_slice($stats['recent_searches'], 0, self::RECENT_SEARCH_LIMIT);

        $route = trim($from . '→' . $to, "→ \t\n\r\0\x0B");
        if ($route !== '') {
            $stats['route_counts'][$route] = (int) ($stats['route_counts'][$route] ?? 0) + 1;
            arsort($stats['route_counts']);
            $stats['route_counts'] = array_slice($stats['route_counts'], 0, self::ROUTE_COUNT_LIMIT, true);
        }

        update_option(self::STATS_OPTION, $stats, false);
    }

    public function register_admin_menu() {
        add_menu_page(
            'Flight Schedule Dashboard',
            'Flight Schedule',
            'manage_options',
            'fst-dashboard',
            [$this, 'render_admin_page'],
            'dashicons-airplane',
            58
        );
    }

    public function register_settings() {
        register_setting('fst_settings_group', self::SETTINGS_OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => $this->default_settings(),
        ]);
    }

    public function sanitize_settings($input) {
        $current = $this->get_settings();
        $out = [];

        $out['embed_js_url'] = isset($input['embed_js_url']) ? esc_url_raw(trim((string) $input['embed_js_url'])) : $current['embed_js_url'];
        $out['api_url'] = isset($input['api_url']) ? esc_url_raw(trim((string) $input['api_url'])) : $current['api_url'];
        $out['subscription_key'] = isset($input['subscription_key']) ? sanitize_text_field((string) $input['subscription_key']) : $current['subscription_key'];
        $out['result_limit'] = isset($input['result_limit']) ? preg_replace('/[^0-9]/', '', (string) $input['result_limit']) : $current['result_limit'];
        $out['default_height'] = isset($input['default_height']) ? preg_replace('/[^0-9]/', '', (string) $input['default_height']) : $current['default_height'];
        $out['use_local_proxy'] = (!empty($input['use_local_proxy']) && $input['use_local_proxy'] === '1') ? '1' : '0';

        if ($out['result_limit'] === '') {
            $out['result_limit'] = '100';
        }
        $result = (int) $out['result_limit'];
        if ($result < 1) {
            $result = 1;
        } elseif ($result > 500) {
            $result = 500;
        }
        $out['result_limit'] = (string) $result;

        if ($out['default_height'] === '') {
            $out['default_height'] = '860';
        }

        return wp_parse_args($out, $this->default_settings());
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_fst-dashboard') {
            return;
        }

        wp_enqueue_style(
            'fst-admin-style',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'fst-admin-script',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            [],
            self::VERSION,
            true
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $stats = $this->get_stats();

        $views = (int) $stats['totals']['views'];
        $renders = (int) $stats['totals']['shortcode_renders'];
        $searches = (int) ($stats['totals']['search_requests'] ?? 0);

        $days = array_keys($stats['by_day']);
        rsort($days);
        $days = array_slice($days, 0, 30);
        $recent_searches = array_slice(is_array($stats['recent_searches']) ? $stats['recent_searches'] : [], 0, 25);
        $route_counts = is_array($stats['route_counts']) ? $stats['route_counts'] : [];
        arsort($route_counts);
        $top_routes = array_slice($route_counts, 0, 15, true);
        ?>
        <div class="wrap fst-admin-wrap">
            <div class="fst-header">
                <h1>Flight Schedule Dashboard</h1>
                <p>Manage widget configuration, monitor usage, and copy shortcode instructions.</p>
            </div>

            <div class="fst-tabs" role="tablist" aria-label="Flight Schedule Tabs">
                <button class="fst-tab is-active" data-tab="kpi" type="button">KPI</button>
                <button class="fst-tab" data-tab="analytics" type="button">Analytics</button>
                <button class="fst-tab" data-tab="settings" type="button">Settings</button>
                <button class="fst-tab" data-tab="instructions" type="button">Instructions</button>
            </div>

            <section class="fst-panel is-active" data-panel="kpi">
                <div class="fst-kpi-grid">
                    <article class="fst-kpi-card">
                        <h3>Total Widget Views</h3>
                        <p class="fst-kpi-value"><?php echo esc_html(number_format_i18n($views)); ?></p>
                    </article>
                    <article class="fst-kpi-card">
                        <h3>Total Shortcode Renders</h3>
                        <p class="fst-kpi-value"><?php echo esc_html(number_format_i18n($renders)); ?></p>
                    </article>
                    <article class="fst-kpi-card">
                        <h3>View/Render Ratio</h3>
                        <p class="fst-kpi-value">
                            <?php
                            $ratio = $renders > 0 ? round(($views / $renders), 2) : 0;
                            echo esc_html($ratio);
                            ?>
                        </p>
                    </article>
                    <article class="fst-kpi-card">
                        <h3>Total Search Requests</h3>
                        <p class="fst-kpi-value"><?php echo esc_html(number_format_i18n($searches)); ?></p>
                    </article>
                </div>
            </section>

            <section class="fst-panel" data-panel="analytics">
                <div class="fst-card">
                    <h2>Daily Usage (Last 30 days)</h2>
                    <table class="fst-table">
                        <thead>
                            <tr>
                                <th>Date (UTC)</th>
                                <th>Views</th>
                                <th>Shortcode Renders</th>
                                <th>Search Requests</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($days)) : ?>
                                <tr>
                                    <td colspan="4">No analytics data yet.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($days as $day) :
                                    $row = $stats['by_day'][$day];
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($day); ?></td>
                                        <td><?php echo esc_html((string) (int) ($row['views'] ?? 0)); ?></td>
                                        <td><?php echo esc_html((string) (int) ($row['shortcode_renders'] ?? 0)); ?></td>
                                        <td><?php echo esc_html((string) (int) ($row['search_requests'] ?? 0)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="fst-card" style="margin-top:14px;">
                    <h2>Top Searched Routes</h2>
                    <table class="fst-table">
                        <thead>
                            <tr>
                                <th>Route</th>
                                <th>Searches</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_routes)) : ?>
                                <tr>
                                    <td colspan="2">No route search data yet.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($top_routes as $route => $count) : ?>
                                    <tr>
                                        <td><?php echo esc_html((string) $route); ?></td>
                                        <td><?php echo esc_html((string) (int) $count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="fst-card" style="margin-top:14px;">
                    <h2>Recent Search Parameters</h2>
                    <table class="fst-table">
                        <thead>
                            <tr>
                                <th>Time (UTC)</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>7-Day</th>
                                <th>Connection</th>
                                <th>Sort</th>
                                <th>Time Filter</th>
                                <th>Airline</th>
                                <th>Result</th>
                                <th>CodeShare</th>
                                <th>Interline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_searches)) : ?>
                                <tr>
                                    <td colspan="11">No search parameter data yet.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($recent_searches as $item) :
                                    $route = trim((string) (($item['from'] ?? '') . '→' . ($item['to'] ?? '')), "→ \t\n\r\0\x0B");
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($item['time_utc'] ?? '')); ?></td>
                                        <td><?php echo esc_html($route); ?></td>
                                        <td><?php echo esc_html((string) ($item['date'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['sevenDay'] ?? 'N')); ?></td>
                                        <td><?php echo esc_html((string) ($item['connection'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['sort'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['time'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['airline'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['result'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['codeshare'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($item['interline'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="fst-panel" data-panel="settings">
                <form method="post" action="options.php" class="fst-card fst-form">
                    <h2>Widget Settings</h2>
                    <?php settings_fields('fst_settings_group'); ?>

                    <label>
                        <span>Embed JS URL</span>
                        <input type="url" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[embed_js_url]" value="<?php echo esc_attr($settings['embed_js_url']); ?>" placeholder="https://cdn.passrider.com/embed.js" required>
                    </label>

                    <label>
                        <span>Flight API URL</span>
                        <input type="url" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[api_url]" value="<?php echo esc_attr($settings['api_url']); ?>" placeholder="https://services.flightlookup.com/v1/xml/TimeTable/" required>
                    </label>

                    <label>
                        <span>Subscription Key</span>
                        <input type="text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[subscription_key]" value="<?php echo esc_attr($settings['subscription_key']); ?>" placeholder="Enter subscription key">
                    </label>

                    <label>
                        <span>Result Limit</span>
                        <input type="number" min="1" max="500" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[result_limit]" value="<?php echo esc_attr($settings['result_limit']); ?>">
                    </label>

                    <label>
                        <span>Default Height (px)</span>
                        <input type="number" min="300" step="10" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[default_height]" value="<?php echo esc_attr($settings['default_height']); ?>">
                    </label>

                    <label class="fst-checkbox">
                        <input type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[use_local_proxy]" value="1" <?php checked($settings['use_local_proxy'], '1'); ?>>
                        <span>Use local WordPress proxy by default (`/wp-json/fst/v1/timetable`)</span>
                    </label>

                    <?php submit_button('Save Settings'); ?>
                </form>
            </section>

            <section class="fst-panel" data-panel="instructions">
                <div class="fst-card">
                    <h2>How to Use</h2>
                    <ol>
                        <li>Set your CDN `embed.js` URL and API key in Settings.</li>
                        <li>Add shortcode to any page/post.</li>
                        <li>The shortcode auto-generates the embed script with your configured defaults.</li>
                    </ol>

                    <h3>Shortcode</h3>
                    <pre>[flight_schedule_widget from="MNL" to="HKG" date="2026-02-20" height="860"]</pre>

                    <h3>Attributes</h3>
                    <ul>
                        <li><code>from</code>: origin code or name/code string (optional)</li>
                        <li><code>to</code>: destination code or name/code string (optional)</li>
                        <li><code>date</code>: YYYY-MM-DD (optional)</li>
                        <li><code>result</code>: maximum API results, e.g. 100 (optional, defaults to Settings)</li>
                        <li><code>height</code>: iframe min height in px (optional)</li>
                        <li><code>target</code>: custom selector target for embed.js (optional)</li>
                    </ul>

                    <h3>REST Endpoints</h3>
                    <ul>
                        <li><code>POST /wp-json/fst/v1/track</code> (analytics tracking)</li>
                        <li><code>GET /wp-json/fst/v1/timetable</code> (CORS-safe proxy)</li>
                    </ul>
                </div>
            </section>
        </div>
        <?php
    }

    private function derive_host_from_embed_url($embed_js_url) {
        $embed_js_url = trim((string) $embed_js_url);
        if ($embed_js_url === '') {
            return '';
        }

        $parts = wp_parse_url($embed_js_url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        $host = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $host .= ':' . (int) $parts['port'];
        }
        if ($dir !== '' && $dir !== '.') {
            $host .= $dir;
        }
        return $host;
    }

    public function render_shortcode($atts = []) {
        $settings = $this->get_settings();

        $atts = shortcode_atts([
            'from' => '',
            'to' => '',
            'date' => '',
            'height' => $settings['default_height'],
            'target' => '',
            'apiurl' => $settings['api_url'],
            'result' => $settings['result_limit'],
            'proxyurl' => '',
        ], $atts, 'flight_schedule_widget');

        $embed_js_url = esc_url(add_query_arg('v', self::VERSION, $settings['embed_js_url']));
        $host = $this->derive_host_from_embed_url($settings['embed_js_url']);
        $proxy = ($settings['use_local_proxy'] === '1') ? rest_url('fst/v1/timetable') : '';

        if (!empty($atts['proxyurl'])) {
            $proxy = $atts['proxyurl'];
        }

        $container_id = 'fst-widget-' . wp_generate_uuid4();
        $target = trim((string) $atts['target']);
        if ($target === '') {
            $target = '#' . $container_id;
        }

        $this->increment_stat('shortcode_renders');

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"></div>
        <script
          src="<?php echo $embed_js_url; ?>"
          data-host="<?php echo esc_attr($host); ?>"
          data-target="<?php echo esc_attr($target); ?>"
          data-height="<?php echo esc_attr($atts['height']); ?>"
          <?php if (!empty($atts['from'])) : ?>data-from="<?php echo esc_attr($atts['from']); ?>"<?php endif; ?>
          <?php if (!empty($atts['to'])) : ?>data-to="<?php echo esc_attr($atts['to']); ?>"<?php endif; ?>
          <?php if (!empty($atts['date'])) : ?>data-date="<?php echo esc_attr($atts['date']); ?>"<?php endif; ?>
          <?php if (!empty($atts['apiurl'])) : ?>data-apiurl="<?php echo esc_attr($atts['apiurl']); ?>"<?php endif; ?>
          <?php if (!empty($atts['result'])) : ?>data-result="<?php echo esc_attr($atts['result']); ?>"<?php endif; ?>
          <?php if (!empty($proxy)) : ?>data-proxyurl="<?php echo esc_attr($proxy); ?>"<?php endif; ?>
        ></script>
        <script>
        (function () {
          var endpoint = <?php echo wp_json_encode(rest_url('fst/v1/track')); ?>;
          try {
            fetch(endpoint, {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({event: 'views'})
            });
          } catch (e) {}

          // Fallback iframe auto-height for shortcode embeds (works even with cached older embed.js).
          var container = document.getElementById(<?php echo wp_json_encode($container_id); ?>);
          if (!container) return;

          function getIframe() {
            return container.querySelector('iframe');
          }

          function applyHeight(value) {
            var iframe = getIframe();
            if (!iframe) return;
            var n = Number(value || 0);
            if (!Number.isFinite(n) || n <= 0) return;
            iframe.style.height = Math.ceil(n) + 'px';
          }

          window.addEventListener('message', function (event) {
            var iframe = getIframe();
            if (!iframe || event.source !== iframe.contentWindow) return;
            var data = event.data || {};
            if (data.type !== 'FST_WIDGET_HEIGHT') return;
            applyHeight(data.height);
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route('fst/v1', '/track', [
            'methods' => ['POST', 'OPTIONS'],
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_track_event'],
        ]);

        register_rest_route('fst/v1', '/timetable', [
            'methods' => ['GET', 'OPTIONS'],
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_timetable_proxy'],
        ]);
    }

    public function rest_track_event(WP_REST_Request $request) {
        if ($request->get_method() === 'OPTIONS') {
            return new WP_REST_Response('', 200);
        }
        $event = sanitize_key((string) $request->get_param('event'));
        if (!in_array($event, ['views', 'shortcode_renders'], true)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Invalid event'], 400);
        }

        $this->increment_stat($event);
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function rest_timetable_proxy(WP_REST_Request $request) {
        if ($request->get_method() === 'OPTIONS') {
            return new WP_REST_Response('', 200);
        }
        $settings = $this->get_settings();

        $from = strtoupper(sanitize_text_field((string) $request->get_param('from')));
        $to = strtoupper(sanitize_text_field((string) $request->get_param('to')));
        $date = preg_replace('/[^0-9]/', '', (string) $request->get_param('date'));

        if ($from === '' || $to === '' || strlen($date) !== 8) {
            return new WP_REST_Response('Missing required params: from, to, date(YYYYMMDD)', 400);
        }

        $connection = sanitize_text_field((string) ($request->get_param('connection') ?: 'AUTO'));
        $sort = sanitize_text_field((string) ($request->get_param('sort') ?: 'Departure'));
        $time = sanitize_text_field((string) ($request->get_param('time') ?: 'ANY'));
        $airline = sanitize_text_field((string) ($request->get_param('airline') ?: '---'));
        $language = sanitize_text_field((string) ($request->get_param('language') ?: 'en'));
        $nofilter = sanitize_text_field((string) ($request->get_param('nofilter') ?: 'Y'));
        $compression = sanitize_text_field((string) ($request->get_param('compression') ?: 'MOST'));
        $codeshare = sanitize_text_field((string) ($request->get_param('codeshare') ?: 'N'));
        $interline = sanitize_text_field((string) ($request->get_param('interline') ?: 'N'));
        $specificDate = sanitize_text_field((string) ($request->get_param('specificDate') ?: 'Y'));
        $result = (int) ($request->get_param('result') ?: $settings['result_limit']);
        if ($result < 1) {
            $result = 1;
        } elseif ($result > 500) {
            $result = 500;
        }

        $this->record_search_analytics([
            'from' => $from,
            'to' => $to,
            'date' => $date,
            'connection' => $connection,
            'sort' => $sort,
            'time' => $time,
            'airline' => $airline,
            'language' => $language,
            'compression' => $compression,
            'codeshare' => $codeshare,
            'interline' => $interline,
            'specificDate' => $specificDate,
            'result' => $result,
        ]);

        $key = sanitize_text_field((string) $settings['subscription_key']);
        if ($key === '') {
            return new WP_REST_Response('Missing subscription key in plugin settings', 400);
        }

        $base = rtrim((string) $settings['api_url'], '/');
        $url = $base . '/' . rawurlencode($from) . '/' . rawurlencode($to) . '/' . rawurlencode($date) . '/?' . http_build_query([
            '7Day' => $specificDate === 'Y' ? 'N' : 'Y',
            'Connection' => $connection,
            'TRC' => 'N',
            'Compression' => $compression,
            'Sort' => $sort,
            'Time' => $time,
            'Airline' => $airline,
            'Language' => $language,
            'Nofilter' => $nofilter,
            'Interline' => $interline,
            'CodeShare' => $codeshare,
            'Results' => $result,
            'subscription-key' => $key,
        ], '', '&', PHP_QUERY_RFC3986);

        $resp = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/xml,text/xml,*/*'],
        ]);

        if (is_wp_error($resp)) {
            return new WP_REST_Response('Proxy request failed', 502);
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $body = $this->normalize_xml_payload($body);

        return new WP_REST_Response($body, $code, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function serve_raw_xml_response($served, $result, $request, $server) {
        if (!($request instanceof WP_REST_Request)) {
            return $served;
        }

        if (strpos($request->get_route(), '/fst/v1/') !== 0) {
            return $served;
        }

        $this->send_cors_headers();

        if ($served) {
            return $served;
        }

        if ($request->get_route() !== '/fst/v1/timetable') {
            return $served;
        }

        $status = 200;
        $body = '';

        if ($result instanceof WP_REST_Response) {
            $status = (int) $result->get_status();
            $body = (string) $result->get_data();
        } else {
            $body = (string) $result;
        }

        $body = $this->normalize_xml_payload($body);

        if (!headers_sent()) {
            status_header($status);
            header('Content-Type: application/xml; charset=utf-8');
        }

        echo $body;
        return true;
    }

    private function send_cors_headers() {
        if (headers_sent()) {
            return;
        }

        $origin = get_http_origin();
        $allow = '*';
        if (!empty($origin)) {
            $allow = $origin;
        }

        header('Access-Control-Allow-Origin: ' . $allow);
        header('Vary: Origin', false);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
    }

    private function normalize_xml_payload($body) {
        $body = (string) $body;
        $trim = ltrim($body);

        if ($trim === '') {
            return $body;
        }

        // Some upstream responses return XML as a JSON-quoted string: "<?xml ...".
        if ($trim[0] === '"') {
            $decoded = json_decode($trim, true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        // Fallback for JSON envelope payloads that wrap the XML.
        if ($trim[0] === '{' || $trim[0] === '[') {
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                $candidates = ['xml', 'XML', 'data', 'payload', 'result', 'body'];
                foreach ($candidates as $key) {
                    if (isset($decoded[$key]) && is_string($decoded[$key]) && strpos($decoded[$key], '<') !== false) {
                        return $decoded[$key];
                    }
                }
            }
        }

        return $body;
    }
}

new FST_Flight_Schedule_Timetable();
