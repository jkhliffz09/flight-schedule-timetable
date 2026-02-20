<?php
/**
 * Plugin Name: Flight Schedule Timetable
 * Description: Responsive flight schedule form and timetable with specific-day and 7-day views.
 * Version: 1.2.0
 * Author: khliffz
 * Update URI: https://github.com/jkhliffz09/flight-schedule-timetable
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-fst-github-updater.php';

final class Flight_Schedule_Timetable_Plugin {
    const VERSION = '1.2.0';
    const OPTION_API_URL = 'fst_api_url';
    const OPTION_SUB_KEY = 'fst_subscription_key';
    const OPTION_METRICS = 'fst_metrics';

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode('flight_schedule_timetable', array($this, 'render_shortcode'));

        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        add_action('wp_ajax_fst_search', array($this, 'handle_search'));
        add_action('wp_ajax_nopriv_fst_search', array($this, 'handle_search'));

        new FST_Github_Updater(__FILE__, self::VERSION, 'jkhliffz09', 'flight-schedule-timetable');
    }

    public function register_assets() {
        wp_register_style(
            'flight-schedule-timetable-gridicons',
            'https://unpkg.com/gridicons@3.3.1/dist/gridicons.min.css',
            array(),
            null
        );

        wp_register_style(
            'flight-schedule-timetable-style',
            plugins_url('assets/css/flight-schedule-timetable.css', __FILE__),
            array('flight-schedule-timetable-gridicons'),
            self::VERSION
        );

        wp_register_script(
            'flight-schedule-timetable-tailwind',
            'https://cdn.tailwindcss.com',
            array(),
            null,
            false
        );

        wp_register_script(
            'flight-schedule-timetable-script',
            plugins_url('assets/js/flight-schedule-timetable.js', __FILE__),
            array(),
            self::VERSION,
            true
        );
    }

    public function render_shortcode($atts = array()) {
        wp_enqueue_style('flight-schedule-timetable-style');
        wp_enqueue_script('flight-schedule-timetable-tailwind');
        wp_enqueue_script('flight-schedule-timetable-script');

        $config = array(
            'loadingDelay' => 1700,
            'currency' => 'USD',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fst_search_nonce')
        );

        wp_localize_script('flight-schedule-timetable-script', 'FlightScheduleTimetableConfig', $config);

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/shortcode.php';
        return ob_get_clean();
    }

    public function register_admin_menu() {
        add_menu_page(
            'Flight Schedule Timetable',
            'Flight Timetable',
            'manage_options',
            'flight-schedule-timetable',
            array($this, 'render_admin_page'),
            'dashicons-airplane',
            58
        );
    }

    public function register_settings() {
        register_setting('fst_settings_group', self::OPTION_API_URL, array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://passrider.prod.flightlookup.com/v1/xml/TimeTable/'
        ));

        register_setting('fst_settings_group', self::OPTION_SUB_KEY, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_sub_key'),
            'default' => 'ee573326b2c34c619eadfff56300ba16'
        ));
    }

    public function sanitize_sub_key($value) {
        return preg_replace('/[^a-zA-Z0-9]/', '', (string) $value);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'kpi';
        if (!in_array($tab, array('kpi', 'analytics', 'settings'), true)) {
            $tab = 'kpi';
        }

        $metrics = $this->get_metrics();
        ?>
        <div class="wrap">
            <h1>Flight Schedule Timetable</h1>
            <h2 class="nav-tab-wrapper" style="margin-top: 16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=flight-schedule-timetable&tab=kpi')); ?>" class="nav-tab <?php echo $tab === 'kpi' ? 'nav-tab-active' : ''; ?>">KPI</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=flight-schedule-timetable&tab=analytics')); ?>" class="nav-tab <?php echo $tab === 'analytics' ? 'nav-tab-active' : ''; ?>">Analytics</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=flight-schedule-timetable&tab=settings')); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
            </h2>

            <?php if ($tab === 'kpi') : ?>
                <?php $this->render_kpi($metrics); ?>
            <?php elseif ($tab === 'analytics') : ?>
                <?php $this->render_analytics($metrics); ?>
            <?php else : ?>
                <?php $this->render_settings(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_kpi($metrics) {
        $total = (int) $metrics['total_searches'];
        $success = (int) $metrics['api_success'];
        $failed = (int) $metrics['api_failed'];
        $rate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
        $avg = $metrics['response_count'] > 0 ? round($metrics['response_total_ms'] / $metrics['response_count']) : 0;
        $last = !empty($metrics['last_search']) ? esc_html($metrics['last_search']) : 'No searches yet';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:18px;">';
        $this->kpi_card('Total Searches', number_format_i18n($total));
        $this->kpi_card('API Success Rate', $rate . '%');
        $this->kpi_card('Avg Response Time', number_format_i18n($avg) . ' ms');
        $this->kpi_card('Last Search', $last);
        echo '</div>';
        echo '<p style="margin-top:14px;color:#4b5563;">Failed calls: ' . esc_html((string) $failed) . '</p>';
    }

    private function kpi_card($title, $value) {
        echo '<div style="background:#fff;border:1px solid #d0d7de;border-radius:10px;padding:16px;">';
        echo '<div style="font-size:12px;color:#6b7280;margin-bottom:6px;">' . esc_html($title) . '</div>';
        echo '<div style="font-size:24px;font-weight:700;color:#111827;">' . esc_html((string) $value) . '</div>';
        echo '</div>';
    }

    private function render_analytics($metrics) {
        $daily = isset($metrics['daily']) && is_array($metrics['daily']) ? $metrics['daily'] : array();
        $routes = isset($metrics['routes']) && is_array($metrics['routes']) ? $metrics['routes'] : array();

        arsort($daily);
        arsort($routes);

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:18px;max-width:1100px;">';

        echo '<div style="background:#fff;border:1px solid #d0d7de;border-radius:10px;padding:16px;">';
        echo '<h3 style="margin-top:0;">Searches by Day</h3>';
        if (empty($daily)) {
            echo '<p>No data yet.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Searches</th></tr></thead><tbody>';
            foreach (array_slice($daily, 0, 14, true) as $day => $count) {
                echo '<tr><td>' . esc_html($day) . '</td><td>' . esc_html((string) $count) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div style="background:#fff;border:1px solid #d0d7de;border-radius:10px;padding:16px;">';
        echo '<h3 style="margin-top:0;">Top Routes</h3>';
        if (empty($routes)) {
            echo '<p>No route data yet.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Route</th><th>Searches</th></tr></thead><tbody>';
            foreach (array_slice($routes, 0, 10, true) as $route => $count) {
                echo '<tr><td>' . esc_html($route) . '</td><td>' . esc_html((string) $count) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '</div>';
    }

    private function render_settings() {
        $api_url = get_option(self::OPTION_API_URL, 'https://passrider.prod.flightlookup.com/v1/xml/TimeTable/');
        $sub_key = get_option(self::OPTION_SUB_KEY, 'ee573326b2c34c619eadfff56300ba16');
        ?>
        <form method="post" action="options.php" style="max-width:760px;margin-top:18px;">
            <?php settings_fields('fst_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="fst_api_url">URL</label></th>
                    <td>
                        <input type="url" id="fst_api_url" name="<?php echo esc_attr(self::OPTION_API_URL); ?>" value="<?php echo esc_attr($api_url); ?>" class="regular-text" style="width:100%;" />
                        <p class="description">Example: https://passrider.prod.flightlookup.com/v1/xml/TimeTable/</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fst_subscription_key">Subscription Key</label></th>
                    <td>
                        <input type="text" id="fst_subscription_key" name="<?php echo esc_attr(self::OPTION_SUB_KEY); ?>" value="<?php echo esc_attr($sub_key); ?>" class="regular-text" style="width:100%;" />
                        <p class="description">Example: ee573326b2c34c619eadfff56300ba16</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
        <?php
    }

    public function handle_search() {
        check_ajax_referer('fst_search_nonce', 'nonce');

        $payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
        $search = json_decode($payload, true);
        if (!is_array($search)) {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        $start = microtime(true);
        $result = $this->fetch_timetable($search);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $this->record_metrics($search, $ms, $result['ok']);

        if (!$result['ok']) {
            wp_send_json_success(array(
                'flights' => $this->fallback_flights($search),
                'total' => 67,
                'apiError' => $result['error']
            ));
        }

        wp_send_json_success(array(
            'flights' => $result['flights'],
            'total' => max(1, count($result['flights'])),
            'apiError' => null
        ));
    }

    private function fetch_timetable($search) {
        $url = trim((string) get_option(self::OPTION_API_URL, ''));
        $sub_key = trim((string) get_option(self::OPTION_SUB_KEY, ''));

        if ($url === '' || $sub_key === '') {
            return array('ok' => false, 'error' => 'Missing API settings', 'flights' => array());
        }

        $request_url = $this->build_timetable_url($url, $sub_key, $search);
        if ($request_url === '') {
            return array('ok' => false, 'error' => 'Invalid route/date values', 'flights' => array());
        }

        $response = wp_remote_get($request_url, array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/xml,text/xml,*/*',
                'Ocp-Apim-Subscription-Key' => $sub_key
            )
        ));

        if (is_wp_error($response)) {
            return array('ok' => false, 'error' => $response->get_error_message(), 'flights' => array());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300 || $raw === '') {
            return array('ok' => false, 'error' => 'HTTP ' . $code, 'flights' => array());
        }

        $flights = $this->parse_flights_from_xml($raw);
        if (empty($flights)) {
            return array('ok' => false, 'error' => 'No flights parsed from API response', 'flights' => array());
        }

        return array('ok' => true, 'error' => null, 'flights' => $flights);
    }

    private function build_timetable_url($base_url, $sub_key, $search) {
        $from = $this->extract_iata(isset($search['from']) ? $search['from'] : '');
        $to = $this->extract_iata(isset($search['to']) ? $search['to'] : '');
        $depart = $this->format_yyyymmdd(isset($search['departDate']) ? $search['departDate'] : '');

        if ($from === '' || $to === '' || $depart === '') {
            return '';
        }

        $path = trailingslashit($base_url) . rawurlencode($from) . '/' . rawurlencode($to) . '/' . rawurlencode($depart) . '/';

        $query = array(
            '7Day' => $this->to_yes_no(isset($search['specificDate']) ? $search['specificDate'] : 'yes', 'N', 'Y'),
            'Connection' => $this->map_connection($search),
            'Count' => 20,
            'TRC' => 'N',
            'Compression' => 'ALL',
            'Sort' => $this->map_sort(isset($search['sortBy']) ? $search['sortBy'] : ''),
            'Time' => $this->map_time(isset($search['time']) ? $search['time'] : ''),
            'Interline' => $this->to_yes_no(isset($search['interline']) ? $search['interline'] : 'no', 'Y', 'N'),
            'CodeShare' => $this->to_yes_no(isset($search['codeshare']) ? $search['codeshare'] : 'no', 'Y', 'N'),
            'subscription-key' => $sub_key
        );

        $airline = strtoupper(trim((string) (isset($search['airlines']) ? $search['airlines'] : '')));
        if ($airline !== '' && $airline !== 'ALL') {
            $query['Airline'] = preg_replace('/[^A-Z0-9]/', '', $airline);
        }

        return add_query_arg($query, $path);
    }

    private function parse_flights_from_xml($raw) {
        $xml = @simplexml_load_string($raw);
        if (!$xml) {
            return array();
        }

        $nodes = $xml->xpath('//*[local-name()="FlightDetails"]');

        if (!$nodes) {
            return array();
        }

        $flights = array();
        foreach ($nodes as $node) {
            $dep_dt = $this->attr_value($node, 'FLSDepartureDateTime');
            $arr_dt = $this->attr_value($node, 'FLSArrivalDateTime');
            $origin = $this->attr_value($node, 'FLSDepartureCode');
            $destination = $this->attr_value($node, 'FLSArrivalCode');
            $duration = $this->duration_to_text($this->attr_value($node, 'TotalFlightTime'));
            $trip_type = $this->attr_value($node, 'FLSFlightType');
            $legs_count = $this->attr_value($node, 'FLSFlightLegs');
            $stops = $this->flight_type_to_stops($trip_type, $legs_count);
            $leg_nodes = $node->xpath('.//*[local-name()="FlightLegDetails"]');
            $airline = $this->extract_airline_name($leg_nodes);

            $flights[] = array(
                'id' => count($flights) + 1,
                'depTime' => trim($this->time_from_iso($dep_dt) . ' ' . $origin),
                'depDate' => $this->date_from_iso($dep_dt),
                'arrTime' => trim($this->time_from_iso($arr_dt) . ' ' . $destination),
                'arrDate' => $this->date_from_iso($arr_dt),
                'duration' => $duration !== '' ? $duration : 'N/A',
                'stops' => $stops !== '' ? $stops : 'N/A',
                'airline' => $airline !== '' ? $airline : 'Unknown Airline',
                'price' => 0,
                'tags' => array(),
                'fare' => 'Economy',
                'layover' => ''
            );
        }

        return array_slice($flights, 0, 100);
    }

    private function extract_airline_name($leg_nodes) {
        if (!is_array($leg_nodes) || empty($leg_nodes)) {
            return '';
        }
        foreach ($leg_nodes as $leg) {
            $carrier = $leg->xpath('.//*[local-name()="MarketingAirline"]');
            if (is_array($carrier) && isset($carrier[0])) {
                $attrs = $carrier[0]->attributes();
                if (isset($attrs['CompanyShortName']) && trim((string) $attrs['CompanyShortName']) !== '') {
                    return (string) $attrs['CompanyShortName'];
                }
                if (isset($attrs['Code']) && trim((string) $attrs['Code']) !== '') {
                    return (string) $attrs['Code'];
                }
            }
        }
        return '';
    }

    private function attr_value($node, $attr_name) {
        $attrs = $node->attributes();
        if (isset($attrs[$attr_name])) {
            return (string) $attrs[$attr_name];
        }
        return '';
    }

    private function extract_iata($input) {
        $value = strtoupper((string) $input);
        if (preg_match('/\\(([A-Z]{3})\\)/', $value, $m)) {
            return $m[1];
        }
        if (preg_match('/\\b([A-Z]{3})\\b/', $value, $m)) {
            return $m[1];
        }
        return '';
    }

    private function format_yyyymmdd($date_value) {
        $value = trim((string) $date_value);
        if ($value === '') {
            return '';
        }
        $value = str_replace('/', '-', $value);
        $ts = strtotime($value);
        if (!$ts) {
            return '';
        }
        return gmdate('Ymd', $ts);
    }

    private function map_connection($search) {
        if (!empty($search['directOnly'])) {
            return 'NONSTOP';
        }
        $value = strtolower(trim((string) (isset($search['stops']) ? $search['stops'] : '')));
        if ($value === 'nonstop') {
            return 'NONSTOP';
        }
        if ($value === '1 stop' || $value === '1stop') {
            return '1STOP';
        }
        if ($value === '2+ stops' || $value === '2 stops') {
            return 'MORE';
        }
        return 'AUTO';
    }

    private function map_sort($sort_by) {
        $value = strtolower(trim((string) $sort_by));
        if ($value === 'arrival' || $value === 'arrival time') {
            return 'Arrival';
        }
        if ($value === 'duration') {
            return 'Duration';
        }
        if ($value === 'flights') {
            return 'Flights';
        }
        return 'Departure';
    }

    private function map_time($time) {
        $value = strtolower(trim((string) $time));
        if ($value === 'morning' || $value === 'am') {
            return 'AM';
        }
        if ($value === 'evening' || $value === 'night') {
            return 'NIGHT';
        }
        if ($value === 'afternoon' || $value === 'pm') {
            return 'PM';
        }
        return 'ANY';
    }

    private function to_yes_no($value, $yes, $no) {
        return strtolower(trim((string) $value)) === 'yes' ? $yes : $no;
    }

    private function time_from_iso($iso) {
        $ts = strtotime((string) $iso);
        if (!$ts) {
            return '';
        }
        return gmdate('H:i', $ts);
    }

    private function date_from_iso($iso) {
        $ts = strtotime((string) $iso);
        if (!$ts) {
            return '';
        }
        return gmdate('Y-m-d', $ts);
    }

    private function duration_to_text($iso_duration) {
        $raw = (string) $iso_duration;
        if ($raw === '') {
            return '';
        }
        if (!preg_match('/^PT(?:(\\d+)H)?(?:(\\d+)M)?$/', $raw, $m)) {
            return $raw;
        }
        $h = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
        $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        return sprintf('%02dh %02dmin', $h, $min);
    }

    private function flight_type_to_stops($flight_type, $legs_count) {
        $type = strtolower((string) $flight_type);
        $legs = (int) $legs_count;
        if ($type === 'nonstop' || $legs <= 1) {
            return 'Direct flight';
        }
        $stops = max(0, $legs - 1);
        return $stops . ($stops === 1 ? ' stop' : ' stops');
    }

    private function fallback_flights($search) {
        $from = !empty($search['from']) ? $search['from'] : 'DVO';
        $to = !empty($search['to']) ? $search['to'] : 'HKG';
        $date = !empty($search['departDate']) ? $search['departDate'] : gmdate('Y-m-d');

        return array(
            array(
                'id' => 1,
                'depTime' => '08:20 ' . $from,
                'depDate' => $date,
                'arrTime' => '11:50 ' . $to,
                'arrDate' => $date,
                'duration' => '03h 30min',
                'stops' => 'Direct flight',
                'airline' => 'Cebu Pacific Air',
                'price' => 285,
                'tags' => array('Recommended', 'Fastest'),
                'fare' => 'Hand baggage',
                'layover' => 'None'
            ),
            array(
                'id' => 2,
                'depTime' => '04:25 ' . $from,
                'depDate' => $date,
                'arrTime' => '11:00 ' . $to,
                'arrDate' => $date,
                'duration' => '06h 35min',
                'stops' => '1 stop',
                'airline' => 'Cebu Pacific Air',
                'price' => 353,
                'tags' => array(),
                'fare' => 'Hand baggage',
                'layover' => 'MNL - 2h 20m'
            )
        );
    }

    private function get_metrics() {
        $defaults = array(
            'total_searches' => 0,
            'api_success' => 0,
            'api_failed' => 0,
            'response_total_ms' => 0,
            'response_count' => 0,
            'last_search' => '',
            'daily' => array(),
            'routes' => array()
        );
        $saved = get_option(self::OPTION_METRICS, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        return wp_parse_args($saved, $defaults);
    }

    private function record_metrics($search, $ms, $success) {
        $metrics = $this->get_metrics();
        $metrics['total_searches']++;
        $metrics['response_total_ms'] += (int) $ms;
        $metrics['response_count']++;
        $metrics['last_search'] = current_time('mysql');

        if ($success) {
            $metrics['api_success']++;
        } else {
            $metrics['api_failed']++;
        }

        $day = current_time('Y-m-d');
        if (!isset($metrics['daily'][$day])) {
            $metrics['daily'][$day] = 0;
        }
        $metrics['daily'][$day]++;

        $from = isset($search['from']) ? trim((string) $search['from']) : '';
        $to = isset($search['to']) ? trim((string) $search['to']) : '';
        if ($from !== '' && $to !== '') {
            $route = $from . ' -> ' . $to;
            if (!isset($metrics['routes'][$route])) {
                $metrics['routes'][$route] = 0;
            }
            $metrics['routes'][$route]++;
        }

        update_option(self::OPTION_METRICS, $metrics, false);
    }
}

new Flight_Schedule_Timetable_Plugin();
