<?php
/**
 * Plugin Name: Flight Schedule Timetable
 * Description: Embed and track the Flight Schedule widget with a custom admin dashboard (KPI, Analytics, Settings, Instructions).
 * Version: 1.1.10
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
    const VERSION = '1.1.10';
    const SETTINGS_OPTION = 'fst_settings';
    const STATS_OPTION = 'fst_stats';
    const DB_VERSION_OPTION = 'fst_db_version';
    const DB_VERSION = '1.0.0';
    const ANALYTICS_PER_PAGE = 10;
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
        add_action('init', [$this, 'maybe_upgrade_storage']);

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

        $this->create_analytics_tables();
        $this->migrate_option_stats_to_tables();
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

    public function maybe_upgrade_storage() {
        $db_version = (string) get_option(self::DB_VERSION_OPTION, '');
        if ($db_version !== self::DB_VERSION) {
            $this->create_analytics_tables();
        }

        if ($this->has_legacy_stats_option() && !$this->table_has_daily_stats_data()) {
            $this->migrate_option_stats_to_tables();
        }
    }

    private function get_table_names() {
        global $wpdb;

        return [
            'daily' => $wpdb->prefix . 'fst_daily_stats',
            'searches' => $wpdb->prefix . 'fst_searches',
            'routes' => $wpdb->prefix . 'fst_route_counts',
        ];
    }

    private function create_analytics_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = $this->get_table_names();
        $charset_collate = $wpdb->get_charset_collate();

        $sql_daily = "CREATE TABLE {$tables['daily']} (
            stat_date date NOT NULL,
            views bigint(20) unsigned NOT NULL DEFAULT 0,
            shortcode_renders bigint(20) unsigned NOT NULL DEFAULT 0,
            search_requests bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (stat_date)
        ) {$charset_collate};";

        $sql_searches = "CREATE TABLE {$tables['searches']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            time_utc datetime NOT NULL,
            from_code varchar(16) NOT NULL DEFAULT '',
            to_code varchar(16) NOT NULL DEFAULT '',
            travel_date char(8) NOT NULL DEFAULT '',
            specific_date char(1) NOT NULL DEFAULT 'Y',
            seven_day char(1) NOT NULL DEFAULT 'N',
            connection varchar(32) NOT NULL DEFAULT 'AUTO',
            sort_by varchar(64) NOT NULL DEFAULT 'Departure',
            time_filter varchar(32) NOT NULL DEFAULT 'ANY',
            airline varchar(64) NOT NULL DEFAULT '---',
            result_limit smallint(5) unsigned NOT NULL DEFAULT 100,
            codeshare char(1) NOT NULL DEFAULT 'N',
            interline char(1) NOT NULL DEFAULT 'N',
            language_code varchar(16) NOT NULL DEFAULT 'en',
            compression varchar(32) NOT NULL DEFAULT 'MOST',
            PRIMARY KEY  (id),
            KEY time_utc (time_utc),
            KEY route_date (from_code, to_code, travel_date)
        ) {$charset_collate};";

        $sql_routes = "CREATE TABLE {$tables['routes']} (
            route_key varchar(64) NOT NULL,
            searches bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (route_key)
        ) {$charset_collate};";

        dbDelta($sql_daily);
        dbDelta($sql_searches);
        dbDelta($sql_routes);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    private function has_legacy_stats_option() {
        $stats = get_option(self::STATS_OPTION, null);
        return is_array($stats) && !empty($stats);
    }

    private function table_has_daily_stats_data() {
        global $wpdb;

        $tables = $this->get_table_names();
        $table_name = $tables['daily'];

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if ($exists !== $table_name) {
            return false;
        }

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        return ((int) $count) > 0;
    }

    private function upsert_daily_row($day, array $increments) {
        global $wpdb;

        $tables = $this->get_table_names();
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT views, shortcode_renders, search_requests FROM {$tables['daily']} WHERE stat_date = %s",
                $day
            ),
            ARRAY_A
        );

        $views = (int) ($increments['views'] ?? 0);
        $renders = (int) ($increments['shortcode_renders'] ?? 0);
        $searches = (int) ($increments['search_requests'] ?? 0);

        if ($existing) {
            $views += (int) $existing['views'];
            $renders += (int) $existing['shortcode_renders'];
            $searches += (int) $existing['search_requests'];
        }

        $wpdb->replace(
            $tables['daily'],
            [
                'stat_date' => $day,
                'views' => $views,
                'shortcode_renders' => $renders,
                'search_requests' => $searches,
            ],
            ['%s', '%d', '%d', '%d']
        );
    }

    private function upsert_route_count($route, $increment = 1) {
        global $wpdb;

        $tables = $this->get_table_names();
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT searches FROM {$tables['routes']} WHERE route_key = %s", $route)
        );
        $count = (int) $existing + (int) $increment;

        $wpdb->replace(
            $tables['routes'],
            [
                'route_key' => $route,
                'searches' => $count,
            ],
            ['%s', '%d']
        );
    }

    private function insert_search_entry(array $entry) {
        global $wpdb;

        $tables = $this->get_table_names();
        $wpdb->insert(
            $tables['searches'],
            [
                'time_utc' => gmdate('Y-m-d H:i:s', strtotime((string) $entry['time_utc'])),
                'from_code' => (string) $entry['from'],
                'to_code' => (string) $entry['to'],
                'travel_date' => (string) $entry['date'],
                'specific_date' => (string) $entry['specificDate'],
                'seven_day' => (string) $entry['sevenDay'],
                'connection' => (string) $entry['connection'],
                'sort_by' => (string) $entry['sort'],
                'time_filter' => (string) $entry['time'],
                'airline' => (string) $entry['airline'],
                'result_limit' => (int) $entry['result'],
                'codeshare' => (string) $entry['codeshare'],
                'interline' => (string) $entry['interline'],
                'language_code' => (string) $entry['language'],
                'compression' => (string) $entry['compression'],
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
    }

    private function migrate_option_stats_to_tables() {
        $stats = get_option(self::STATS_OPTION, []);
        if (!is_array($stats) || empty($stats)) {
            return;
        }

        $by_day = isset($stats['by_day']) && is_array($stats['by_day']) ? $stats['by_day'] : [];
        foreach ($by_day as $day => $row) {
            $this->upsert_daily_row($day, [
                'views' => (int) ($row['views'] ?? 0),
                'shortcode_renders' => (int) ($row['shortcode_renders'] ?? 0),
                'search_requests' => (int) ($row['search_requests'] ?? 0),
            ]);
        }

        $recent_searches = isset($stats['recent_searches']) && is_array($stats['recent_searches']) ? array_reverse($stats['recent_searches']) : [];
        foreach ($recent_searches as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalized = [
                'time_utc' => (string) ($entry['time_utc'] ?? gmdate('c')),
                'from' => strtoupper(sanitize_text_field((string) ($entry['from'] ?? ''))),
                'to' => strtoupper(sanitize_text_field((string) ($entry['to'] ?? ''))),
                'date' => preg_replace('/[^0-9]/', '', (string) ($entry['date'] ?? '')),
                'specificDate' => $this->normalize_yn($entry['specificDate'] ?? 'Y', 'Y'),
                'sevenDay' => $this->normalize_yn($entry['sevenDay'] ?? 'N', 'N'),
                'connection' => sanitize_text_field((string) ($entry['connection'] ?? 'AUTO')),
                'sort' => sanitize_text_field((string) ($entry['sort'] ?? 'Departure')),
                'time' => sanitize_text_field((string) ($entry['time'] ?? 'ANY')),
                'airline' => sanitize_text_field((string) ($entry['airline'] ?? '---')),
                'result' => (string) max(1, min(500, (int) ($entry['result'] ?? 100))),
                'codeshare' => $this->normalize_yn($entry['codeshare'] ?? 'N', 'N'),
                'interline' => $this->normalize_yn($entry['interline'] ?? 'N', 'N'),
                'language' => sanitize_text_field((string) ($entry['language'] ?? 'en')),
                'compression' => sanitize_text_field((string) ($entry['compression'] ?? 'MOST')),
            ];

            $this->insert_search_entry($normalized);
        }

        $route_counts = isset($stats['route_counts']) && is_array($stats['route_counts']) ? $stats['route_counts'] : [];
        foreach ($route_counts as $route => $count) {
            $route = sanitize_text_field((string) $route);
            if ($route === '') {
                continue;
            }

            $this->upsert_route_count($route, (int) $count);
        }
    }

    private function get_stats() {
        global $wpdb;

        $tables = $this->get_table_names();
        $daily_table = $tables['daily'];
        $searches_table = $tables['searches'];
        $routes_table = $tables['routes'];

        $daily_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $daily_table)) === $daily_table;
        $searches_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $searches_table)) === $searches_table;
        $routes_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $routes_table)) === $routes_table;

        if ($daily_exists && $searches_exists && $routes_exists) {
            $daily_rows = $wpdb->get_results(
                "SELECT stat_date, views, shortcode_renders, search_requests
                 FROM {$daily_table}
                 ORDER BY stat_date DESC",
                ARRAY_A
            );

            $by_day = [];
            $totals = [
                'views' => 0,
                'shortcode_renders' => 0,
                'search_requests' => 0,
            ];

            foreach ($daily_rows as $row) {
                $day = (string) $row['stat_date'];
                $views = (int) $row['views'];
                $renders = (int) $row['shortcode_renders'];
                $searches = (int) $row['search_requests'];

                $by_day[$day] = [
                    'views' => $views,
                    'shortcode_renders' => $renders,
                    'search_requests' => $searches,
                ];

                $totals['views'] += $views;
                $totals['shortcode_renders'] += $renders;
                $totals['search_requests'] += $searches;
            }

            $recent_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT time_utc, from_code, to_code, travel_date, specific_date, seven_day, connection, sort_by, time_filter, airline, result_limit, codeshare, interline, language_code, compression
                     FROM {$searches_table}
                     ORDER BY time_utc DESC, id DESC
                     LIMIT %d",
                    self::RECENT_SEARCH_LIMIT
                ),
                ARRAY_A
            );

            $recent_searches = [];
            foreach ($recent_rows as $row) {
                $recent_searches[] = [
                    'time_utc' => gmdate('c', strtotime((string) $row['time_utc'] . ' UTC')),
                    'from' => (string) $row['from_code'],
                    'to' => (string) $row['to_code'],
                    'date' => (string) $row['travel_date'],
                    'specificDate' => (string) $row['specific_date'],
                    'sevenDay' => (string) $row['seven_day'],
                    'connection' => (string) $row['connection'],
                    'sort' => (string) $row['sort_by'],
                    'time' => (string) $row['time_filter'],
                    'airline' => (string) $row['airline'],
                    'result' => (string) $row['result_limit'],
                    'codeshare' => (string) $row['codeshare'],
                    'interline' => (string) $row['interline'],
                    'language' => (string) $row['language_code'],
                    'compression' => (string) $row['compression'],
                ];
            }

            $route_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT route_key, searches
                     FROM {$routes_table}
                     ORDER BY searches DESC, route_key ASC
                     LIMIT %d",
                    self::ROUTE_COUNT_LIMIT
                ),
                ARRAY_A
            );

            $route_counts = [];
            foreach ($route_rows as $row) {
                $route_counts[(string) $row['route_key']] = (int) $row['searches'];
            }

            return [
                'totals' => $totals,
                'by_day' => $by_day,
                'recent_searches' => $recent_searches,
                'route_counts' => $route_counts,
            ];
        }

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

        $day = gmdate('Y-m-d');
        $this->upsert_daily_row($day, [$event => 1]);
    }

    private function normalize_yn($value, $default = 'N') {
        $v = strtoupper(sanitize_text_field((string) $value));
        if ($v === 'Y' || $v === 'N') {
            return $v;
        }
        return $default;
    }

    private function render_pagination($target_id, $total_items, $per_page = self::ANALYTICS_PER_PAGE) {
        $total_pages = max(1, (int) ceil(((int) $total_items) / $per_page));
        if ($total_pages <= 1) {
            return;
        }

        echo '<div class="fst-pagination">';

        for ($page = 1; $page <= $total_pages; $page++) {
            $class = 'fst-page-link';
            if ($page === 1) {
                $class .= ' is-active';
            }

            printf(
                '<button class="%s" type="button" data-fst-page-target="%s" data-fst-page="%d">%d</button>',
                esc_attr($class),
                esc_attr($target_id),
                (int) $page,
                (int) $page
            );
        }

        echo '</div>';
    }

    private function record_search_analytics(array $payload) {
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

        $this->upsert_daily_row($day, ['search_requests' => 1]);
        $this->insert_search_entry($entry);

        $route = trim($from . '→' . $to, "→ \t\n\r\0\x0B");
        if ($route !== '') {
            $this->upsert_route_count($route, 1);
        }
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
        $recent_searches = array_slice(is_array($stats['recent_searches']) ? $stats['recent_searches'] : [], 0, 25);
        $route_counts = is_array($stats['route_counts']) ? $stats['route_counts'] : [];
        arsort($route_counts);
        $route_keys = array_keys($route_counts);
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
                <div class="fst-analytics-grid">
                    <div class="fst-card">
                        <h2>Daily Usage</h2>
                        <table class="fst-table">
                            <thead>
                                <tr>
                                    <th>Date (UTC)</th>
                                    <th>Views</th>
                                    <th>Shortcode Renders</th>
                                    <th>Search Requests</th>
                                </tr>
                            </thead>
                            <tbody id="fst-daily-usage-table">
                                <?php if (empty($days)) : ?>
                                    <tr>
                                        <td colspan="4">No analytics data yet.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($days as $index => $day) :
                                        $page = (int) floor($index / self::ANALYTICS_PER_PAGE) + 1;
                                        $row = $stats['by_day'][$day];
                                        ?>
                                        <tr data-fst-page-row="<?php echo esc_attr('fst-daily-usage-table'); ?>" data-fst-page="<?php echo esc_attr((string) $page); ?>"<?php if ($page > 1) : ?> hidden<?php endif; ?>>
                                            <td><?php echo esc_html($day); ?></td>
                                            <td><?php echo esc_html((string) (int) ($row['views'] ?? 0)); ?></td>
                                            <td><?php echo esc_html((string) (int) ($row['shortcode_renders'] ?? 0)); ?></td>
                                            <td><?php echo esc_html((string) (int) ($row['search_requests'] ?? 0)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php $this->render_pagination('fst-daily-usage-table', count($days)); ?>
                    </div>

                    <div class="fst-card">
                        <h2>Top Searched Routes</h2>
                        <table class="fst-table">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Searches</th>
                                </tr>
                            </thead>
                            <tbody id="fst-route-counts-table">
                                <?php if (empty($route_keys)) : ?>
                                    <tr>
                                        <td colspan="2">No route search data yet.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($route_keys as $index => $route) :
                                        $page = (int) floor($index / self::ANALYTICS_PER_PAGE) + 1;
                                        ?>
                                        <tr data-fst-page-row="<?php echo esc_attr('fst-route-counts-table'); ?>" data-fst-page="<?php echo esc_attr((string) $page); ?>"<?php if ($page > 1) : ?> hidden<?php endif; ?>>
                                            <td><?php echo esc_html((string) $route); ?></td>
                                            <td><?php echo esc_html((string) (int) ($route_counts[$route] ?? 0)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php $this->render_pagination('fst-route-counts-table', count($route_keys)); ?>
                    </div>
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
                        <tbody id="fst-recent-searches-table">
                            <?php if (empty($recent_searches)) : ?>
                                <tr>
                                    <td colspan="11">No search parameter data yet.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($recent_searches as $index => $item) :
                                    $page = (int) floor($index / self::ANALYTICS_PER_PAGE) + 1;
                                    $route = trim((string) (($item['from'] ?? '') . '→' . ($item['to'] ?? '')), "→ \t\n\r\0\x0B");
                                    ?>
                                    <tr data-fst-page-row="<?php echo esc_attr('fst-recent-searches-table'); ?>" data-fst-page="<?php echo esc_attr((string) $page); ?>"<?php if ($page > 1) : ?> hidden<?php endif; ?>>
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
                    <?php $this->render_pagination('fst-recent-searches-table', count($recent_searches)); ?>
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
        $email_url = rest_url('fst/v1/email');

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
          data-emailurl="<?php echo esc_attr($email_url); ?>"
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

        register_rest_route('fst/v1', '/email', [
            'methods' => ['POST', 'OPTIONS'],
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_email_schedule'],
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

    public function rest_email_schedule(WP_REST_Request $request) {
        if ($request->get_method() === 'OPTIONS') {
            return new WP_REST_Response('', 200);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $to_email = sanitize_email((string) ($params['toEmail'] ?? ''));
        $payload = isset($params['payload']) && is_array($params['payload']) ? $params['payload'] : [];
        $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
        $results = isset($payload['results']) && is_array($payload['results']) ? array_slice($payload['results'], 0, 200) : [];
        $page_url = esc_url_raw((string) ($payload['pageUrl'] ?? ''));

        if ($to_email === '' || !is_email($to_email)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'A valid email address is required.'], 400);
        }

        if (empty($summary) || empty($results)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'There are no results to email.'], 400);
        }

        $origin_name = sanitize_text_field((string) ($summary['originName'] ?? ''));
        $origin_code = strtoupper(sanitize_text_field((string) ($summary['originCode'] ?? '')));
        $destination_name = sanitize_text_field((string) ($summary['destinationName'] ?? ''));
        $destination_code = strtoupper(sanitize_text_field((string) ($summary['destinationCode'] ?? '')));
        $date_label = sanitize_text_field((string) ($summary['dateLabel'] ?? ''));
        $total_results = (int) ($summary['totalResults'] ?? 0);

        $subject_route = trim($origin_code . ' to ' . $destination_code);
        $subject = $subject_route !== '' ? 'Flight Schedule: ' . $subject_route : 'Flight Schedule';

        $html = '<div style="font-family:Arial,sans-serif;color:#111111;line-height:1.5;">';
        $html .= '<h2 style="margin:0 0 16px;">Flight Schedule</h2>';
        $html .= '<p style="margin:0 0 4px;"><strong>Date of Flight:</strong> ' . esc_html($date_label) . '</p>';
        $html .= '<p style="margin:0 0 4px;"><strong>From:</strong> ' . esc_html(trim($origin_name . ' (' . $origin_code . ')')) . '</p>';
        $html .= '<p style="margin:0 0 4px;"><strong>To:</strong> ' . esc_html(trim($destination_name . ' (' . $destination_code . ')')) . '</p>';
        $html .= '<p style="margin:0 0 16px;"><strong>Total Results:</strong> ' . esc_html((string) $total_results) . '</p>';

        foreach ($results as $index => $result) {
            if (!is_array($result)) {
                continue;
            }

            $dep_time = sanitize_text_field((string) ($result['depTime'] ?? ''));
            $dep_indicator = sanitize_text_field((string) ($result['depDayIndicator'] ?? ''));
            $dep_code = strtoupper(sanitize_text_field((string) ($result['depCode'] ?? '')));
            $dep_date = sanitize_text_field((string) ($result['depDate'] ?? ''));
            $arr_time = sanitize_text_field((string) ($result['arrTime'] ?? ''));
            $arr_indicator = sanitize_text_field((string) ($result['arrDayIndicator'] ?? ''));
            $arr_code = strtoupper(sanitize_text_field((string) ($result['arrCode'] ?? '')));
            $arr_date = sanitize_text_field((string) ($result['arrDate'] ?? ''));
            $duration = sanitize_text_field((string) ($result['duration'] ?? ''));
            $stops = sanitize_text_field((string) ($result['stops'] ?? ''));
            $airline = sanitize_text_field((string) ($result['airline'] ?? ''));
            $segments = isset($result['segments']) && is_array($result['segments']) ? array_slice($result['segments'], 0, 10) : [];

            $html .= '<div style="border:1px solid #d1d5db;border-radius:16px;padding:16px;margin:0 0 16px;">';
            $html .= '<p style="margin:0 0 8px;"><strong>Result ' . esc_html((string) ($index + 1)) . ':</strong> ' . esc_html($airline) . '</p>';
            $html .= '<p style="margin:0 0 4px;"><strong>Departure:</strong> ' . esc_html(trim($dep_time . ' ' . $dep_indicator)) . ' • ' . esc_html($dep_code) . ' • ' . esc_html($dep_date) . '</p>';
            $html .= '<p style="margin:0 0 4px;"><strong>Arrival:</strong> ' . esc_html(trim($arr_time . ' ' . $arr_indicator)) . ' • ' . esc_html($arr_code) . ' • ' . esc_html($arr_date) . '</p>';
            $html .= '<p style="margin:0 0 12px;"><strong>Duration:</strong> ' . esc_html($duration) . ' | <strong>Stops:</strong> ' . esc_html($stops) . '</p>';

            if (!empty($segments)) {
                $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                $html .= '<thead><tr>';
                $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Flight</th>';
                $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Route</th>';
                $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Time</th>';
                $html .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Equipment</th>';
                $html .= '</tr></thead><tbody>';

                foreach ($segments as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }

                    $segment_airline_name = sanitize_text_field((string) ($segment['airlineName'] ?? ''));
                    $segment_airline_code = strtoupper(sanitize_text_field((string) ($segment['airlineCode'] ?? '')));
                    $segment_flight_number = sanitize_text_field((string) ($segment['flightNumber'] ?? ''));
                    $segment_aircraft = sanitize_text_field((string) ($segment['airEquipType'] ?? ''));
                    $segment_from = sanitize_text_field((string) ($segment['fromLabel'] ?? ''));
                    $segment_to = sanitize_text_field((string) ($segment['toLabel'] ?? ''));
                    $segment_dep_time = sanitize_text_field((string) ($segment['depTime'] ?? ''));
                    $segment_dep_indicator = sanitize_text_field((string) ($segment['depDayIndicator'] ?? ''));
                    $segment_arr_time = sanitize_text_field((string) ($segment['arrTime'] ?? ''));
                    $segment_arr_indicator = sanitize_text_field((string) ($segment['arrDayIndicator'] ?? ''));
                    $segment_dep_terminal = sanitize_text_field((string) ($segment['depTerminal'] ?? ''));
                    $segment_arr_terminal = sanitize_text_field((string) ($segment['arrTerminal'] ?? ''));
                    $segment_operated_by = sanitize_text_field((string) ($segment['operatedBy'] ?? ''));
                    $segment_duration = sanitize_text_field((string) ($segment['duration'] ?? ''));

                    $flight_label = trim($segment_airline_name . ' ' . trim($segment_airline_code . $segment_flight_number));
                    if ($segment_operated_by !== '') {
                        $flight_label .= '<br><span style="color:#1d4ed8;font-size:12px;">' . esc_html($segment_operated_by) . '</span>';
                    }

                    $route_label = esc_html($segment_from);
                    if ($segment_dep_terminal !== '') {
                        $route_label .= '<br><span style="font-size:12px;color:#4b5563;">' . esc_html($segment_dep_terminal) . '</span>';
                    }
                    $route_label .= '<br>&rarr;<br>' . esc_html($segment_to);
                    if ($segment_arr_terminal !== '') {
                        $route_label .= '<br><span style="font-size:12px;color:#4b5563;">' . esc_html($segment_arr_terminal) . '</span>';
                    }

                    $time_label = esc_html(trim($segment_dep_time . ' ' . $segment_dep_indicator));
                    $time_label .= '<br><span style="font-size:12px;color:#4b5563;">' . esc_html($segment_duration) . '</span>';
                    $time_label .= '<br>' . esc_html(trim($segment_arr_time . ' ' . $segment_arr_indicator));

                    $html .= '<tr>';
                    $html .= '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' . $flight_label . '</td>';
                    $html .= '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' . $route_label . '</td>';
                    $html .= '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' . $time_label . '</td>';
                    $html .= '<td style="padding:8px;border-bottom:1px solid #f3f4f6;">' . esc_html($segment_aircraft) . '</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody></table>';
            }

            $html .= '</div>';
        }

        if ($page_url !== '') {
            $html .= '<p style="margin-top:16px;font-size:12px;color:#4b5563;">Source: <a href="' . esc_url($page_url) . '">' . esc_html($page_url) . '</a></p>';
        }

        $html .= '</div>';

        $from_filter = function () {
            return 'feedback@passrider.com';
        };
        $from_name_filter = function () {
            return 'Passrider Flight Schedule';
        };
        $content_type_filter = function () {
            return 'text/html';
        };

        add_filter('wp_mail_from', $from_filter);
        add_filter('wp_mail_from_name', $from_name_filter);
        add_filter('wp_mail_content_type', $content_type_filter);
        $sent = wp_mail($to_email, $subject, $html);
        remove_filter('wp_mail_from', $from_filter);
        remove_filter('wp_mail_from_name', $from_name_filter);
        remove_filter('wp_mail_content_type', $content_type_filter);

        if (!$sent) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Unable to send email.'], 500);
        }

        return new WP_REST_Response(['ok' => true], 200);
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
