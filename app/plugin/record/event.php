<?php
/**
 * Record processing plugin for "Quality/AEO Events"
 *
 * PHP 8.3 hardened version
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2025.01.01
 */
class App_Plugin_Record_Event extends App_Model_Statistics implements App_Plugin_Record
{
    public function __construct()
    {
        if (!isset(self::$cache_ttl['records_per_group'])) {
            self::$cache_ttl['records_per_group'] = 3600; // Update per hour
        }
    }

    private static $risk_matrix = [
        // Columns: Severity, Rows: Probability
        "1" => [-1 => 1, 1 => 1, 2 => 2, 3 => 3, 4 => 3],
        "2" => [-1 => 1, 1 => 2, 2 => 3, 3 => 3, 4 => 4],
        "3" => [-1 => 2, 1 => 3, 2 => 3, 3 => 4, 4 => 5],
        "4" => [-1 => 3, 1 => 3, 2 => 4, 3 => 5, 4 => 6],
        "5" => [-1 => 3, 1 => 4, 2 => 5, 3 => 6, 4 => 6]
    ];

    /**
     * Auto calculations on update of record
     */
    public function process_Data($rec_id, &$metadata, &$xfields)
    {
        // Currency copy
        if (!empty($xfields['frisk']['int_value'])) {
            $val = $xfields['frisk']['int_value'];
            foreach (['fmaxli','fins','fprov','fsug','frisk2','fchback','finv','fsol'] as $f) {
                $xfields[$f]['int_value'] = $val;
            }
        }

        // ROI defaults
        $xfields['roi_potential']['value'] = null;
        $xfields['roi_actual']['value'] = null;

        if (!empty($xfields['finv']['value']) || !empty($xfields['fsol']['value'])) {
            $inv_sol =
                (isset($xfields['finv']['value']) ? (float)$xfields['finv']['value'] : 0)
              + (isset($xfields['fsol']['value']) ? (float)$xfields['fsol']['value'] : 0);

            if (!empty($xfields['frisk']['value']) && $inv_sol != 0) {
                $xfields['roi_potential']['value'] = round((($xfields['frisk']['value'] - $inv_sol) / $inv_sol) * 100, 2);
            }

            if (!empty($xfields['frisk2']['value']) && $inv_sol != 0) {
                $xfields['roi_actual']['value'] = round((($xfields['frisk2']['value'] - $inv_sol) / $inv_sol) * 100, 2);
            }
        }

        // Event Followup
        if (!empty($xfields['severity']['int_value'])) {
            $inv_done_binary = [
                'no'  => false,
                'rc'  => false,
                'req' => false,
                'ds'  => false,
                'st'  => false,
                'end' => false,
            ];

            $severity    = (int)$xfields['severity']['int_value'];
            $probability = isset($xfields['probability']['int_value']) ? (int)$xfields['probability']['int_value'] : null;

            if ($probability !== null && isset(self::$risk_matrix[$probability][$severity])) {
                $risk = (int)self::$risk_matrix[$probability][$severity];
                $xfields['risk']['int_value'] = $risk;

                if ($risk <= 2) {
                    $inv_done_binary['no'] = true;
                } elseif ($risk === 3) {
                    $inv_done_binary['rc'] = true;
                } else {
                    $inv_done_binary['req'] = true;
                }
            } else {
                // Severity-only fallback
                if ($severity <= 1) {
                    $inv_done_binary['no'] = true;
                    $xfields['risk']['int_value'] = 2;
                } elseif ($severity === 2) {
                    $inv_done_binary['rc'] = true;
                    $xfields['risk']['int_value'] = 3;
                } elseif ($severity === 3) {
                    $inv_done_binary['req'] = true;
                    $xfields['risk']['int_value'] = 4;
                } elseif ($severity >= 4) {
                    $inv_done_binary['req'] = true;
                    $xfields['risk']['int_value'] = 6;
                }
            }

            // UPDATE mode
            if (!empty($rec_id)) {
                $inv_cb_user = [];
                if (!empty($xfields['inv_done']['value'])) {
                    $decoded = json_decode($xfields['inv_done']['value'], true);
                    if (is_array($decoded)) {
                        $inv_cb_user = $decoded;
                    }
                }

                if (!empty($xfields['inv_date']['int_value']) || in_array('ds', $inv_cb_user, true)) {
                    $inv_done_binary['ds'] = true;
                }
                if (!empty($xfields['inv_start_date']['int_value']) || in_array('st', $inv_cb_user, true)) {
                    $inv_done_binary['st'] = true;
                }
                if (!empty($xfields['inv_end_date']['int_value']) || in_array('end', $inv_cb_user, true)) {
                    $inv_done_binary['end'] = true;
                }
            }

            $xfields['inv_done']['value'] = json_encode(array_keys(array_filter($inv_done_binary)));
        }
    }

    /**
     * Setup chart configuration
     */
    protected function ccfg_records_per_group($rt_id, $params)
    {
        if (empty($this->user_groups)) {
            $this->user_groups = self::user_Available_Groups($rt_id);
        }

        foreach ($this->user_groups as &$group) {
            if (isset($group['name']) && preg_match('/^[A-Z]{3} \|/', $group['name'])) {
                $group['name'] = substr($group['name'], 0, 3);
            }
        }

        $this->chart_config['name'] = 'Events per Category';
        return $this->ccfg_Setup_Period($params['period'], $this->chart_config['name']);
    }
}
