<?php
/**
 * Field plugin for storing monetary value (Currency)
 * - Stores data in both columns of "ixf", int_value is the currency ( 1 = USD, 2 = EUR, etc... ), and amount in "value"
 * - List of available currencies are stored in the "cd" field, one per line (ISO is used). Example: USR EUR GBP ; If missing, all available currencies are shown.
 * - "Default" value refers to the currency selector, and not the the amount of money (That always starts empty)
 * @see https://en.wikipedia.org/wiki/ISO_4217
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Plugins
 * @version 2.7.1
 */
class App_Plugin_Field_Money
{
    public $name = "Money Amount";

    private static $_currencies = array();
    private static $_currency_iso_index = array();

    public function __construct()
    {
        if (empty(self::$_currencies)) {
            $currency_data = MVC_Core::$spyc->loadFile(
                ROOT . DS . 'app' . DS . 'data' . DS . 'currencies.yaml'
            );

            foreach ($currency_data as &$entry) {
                self::$_currencies[(int)$entry['curr']] = $entry;
                self::$_currency_iso_index[$entry['iso']] = $entry;
            }
        }
    }

    public $fdef;
    public $data_column = "value";

    /* ================= VALIDATION ================= */

    public function check(&$fdata, $update_mode = false)
    {
        $fdata['amount'] = trim((string)($fdata['amount'] ?? ''));

        if ($update_mode && $fdata['amount'] === '') {
            return false;
        }

        if (
            !empty($this->fdef['o']['req']) &&
            $fdata['amount'] === ''
        ) {
            return "Currency amount must be entered";
        }

        if ($fdata['amount'] !== '') {
            if (!preg_match("/^[0-9]+(?:\\.[0-9]{1,2})?$/", $fdata['amount'])) {
                return "The entered amount must be a valid positive number";
            }

            $fdata['curr'] = (int)($fdata['curr'] ?? 0);

            if ($fdata['curr'] === 0) {
                return "The currency has not been selected";
            }

            if (!isset(self::$_currencies[$fdata['curr']])) {
                return "Invalid currency selected. Possible template error";
            }
        }

        return false;
    }

    /* ================= STORAGE ================= */

    public function get_Field_Store(&$fdata)
    {
        $amount = trim((string)($fdata['amount'] ?? ''));

        if ($amount === '') {
            return array();
        }

        return array(
            'int_value' => isset($fdata['curr']) ? (int)$fdata['curr'] : null,
            'value'     => $amount
        );
    }

    public function get_Default()
    {
        return array(
            'value'     => 0,
            'int_value' => self::$_currency_iso_index[$this->fdef['o']['def']]['curr'] ?? null
        );
    }

    /* ================= TEMPLATE ================= */

    public function get_Serialized_Placeholders($key, $primary_field_name = null, $group = null)
    {
        $primary_field_name = $primary_field_name ?: "Money";

        return array(
            array(
                'n' => $primary_field_name . " / Value",
                'group' => $group,
                'ph' => '{' . $key . '.amtf}'
            ),
            array(
                'n' => $primary_field_name . ' / Currency',
                'group' => $group,
                'ph' => '{' . $key . '.curr}'
            )
        );
    }

    /* ================= DECODE ================= */

    public function decode($xf, $serialize = false)
    {
        $currency_id = (int)($xf['int_value'] ?? 0);

        $curr_row = self::$_currencies[$currency_id] ?? array(
            'curr' => 0,
            'name' => 'Unknown',
            'iso'  => '(?)',
            'sym'  => ''
        );

        $value = trim((string)($xf['value'] ?? ''));

        if ($value !== '') {
            $value = str_replace(',', '.', $value);
            $amount = (float)$value;

            $amtf = number_format($amount, 2, '.', '');

            if (!empty($this->fdef['o']['colr'])) {
                $serialized = !empty($curr_row['sym_before'])
                    ? $curr_row['sym'] . $amtf
                    : $amtf . ' ' . $curr_row['sym'];
                $curr = $curr_row['sym'];
            } else {
                $serialized = $amtf . ' ' . $curr_row['iso'];
                $curr = $curr_row['iso'];
            }

            $fake_serialize = array(
                'amtf' => $amtf,
                'curr' => $curr
            );
        } else {
            $amtf = null;
            $serialized = null;
            $fake_serialize = null;
        }

        return $serialize
            ? $fake_serialize
            : array_merge(
                $curr_row,
                array(
                    'amount' => $value ?: null,
                    'amount_format' => $amtf,
                    'serialized' => $serialized
                )
            );
    }

    /* ================= FORM ================= */

    public function setup_Form(MVC_Form &$form, $key, &$xfields)
    {
        $value = is_array($xfields) && isset($xfields[$key]) ? $xfields[$key] : null;

        if ($value !== null) {
            $default = $value;
        } elseif (!empty($this->fdef['o']['def'])) {
            $default = array(
                'amount' => '',
                'curr' => self::$_currency_iso_index[$this->fdef['o']['def']]['curr'] ?? null
            );
        } else {
            $default = array('amount' => '', 'curr' => null);
        }

        $required = !empty($this->fdef['o']['req']);

        $currencies = array();
        foreach (self::$_currencies as $c_id => $currency) {
            if (
                empty($this->fdef['cd_ex']) ||
                isset($this->fdef['cd_ex'][$currency['iso']])
            ) {
                $currencies[$c_id] = $currency['iso'] . ' ' . $currency['sym'];
            }
        }

        $form->add_Element(
            "Scalar",
            $key,
            'amount',
            $default['amount'],
            null,
            "xfd.$key",
            array('label' => $this->fdef['n'], 'req' => $required)
        );

        $form->add_Element(
            "Enum",
            $key . '_curr',
            'curr',
            $default['curr'],
            $currencies,
            "xfd.$key",
            array('label' => $this->fdef['n'] . " Currency")
        );
    }

    /* ================= COMPAT ================= */

    public function form_Compat($xfield, $key)
    {
        $value = trim((string)($xfield['value'] ?? ''));

        return $value !== ''
            ? array(
                'amount' => str_replace(',', '.', $value),
                'curr' => (int)$xfield['int_value']
            )
            : array(
                'amount' => null,
                'curr' => null
            );
    }

    /* ================= SEARCH ================= */

    public function prepare_Data_Search(&$filter_item, &$filter_info)
    {
        $value = trim((string)($filter_item['v'] ?? ''));

        if ($value === '+') {
            $filter_item['c'] = "NOT_EMPTY";
            return;
        }

        preg_match("/([=><~]?)([0-9.]+) ?(.*)/i", $value, $matches);

        if (empty($matches)) {
            $filter_item['v'] = null;
            return;
        }

        $num = (float)$matches[2];
        $filter_item['cast'] = "decimal";

        switch ($matches[1]) {
            case '=':
                $filter_item['c'] = "BETWEEN";
                $filter_item['v'] = array(
                    number_format(floor($num), 2, '.', ''),
                    number_format(floor($num) + 0.99, 2, '.', '')
                );
                break;
            case '>':
                $filter_item['c'] = "GREATER_THAN_OR_EQUAL";
                $filter_item['v'] = number_format($num - 0.005, 2, '.', '');
                break;
            case '<':
                $filter_item['c'] = "LESS_THAN_OR_EQUAL";
                $filter_item['v'] = number_format($num + 0.005, 2, '.', '');
                break;
            case '~':
                $filter_item['c'] = "BETWEEN";
                $filter_item['v'] = array(
                    number_format($num * 0.95, 2, '.', ''),
                    number_format($num * 1.05, 2, '.', '')
                );
                break;
            default:
                $filter_item['c'] = "BETWEEN";
                $filter_item['v'] = array(
                    number_format($num * 0.995, 2, '.', ''),
                    number_format($num * 1.005, 2, '.', '')
                );
        }
    }
}
