<?php
class Coin_Table_Widget extends \Elementor\Widget_Base
{

    public function get_name()
    {
        return 'coin_table_widget';
    }

    public function get_title()
    {
        return 'Coin Table';
    }

    public function get_icon()
    {
        return 'eicon-price-table';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Settings',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'api-key',
            [
                'label' => 'Gecko Api Key',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => 'Enter you Api Key',
            ]
        );

        $this->add_control(
            'coins',
            [
                'label' => 'Coins to list',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'DOGE',
                'placeholder' => 'Enter coins tag separated with comma',
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $coins = esc_html($settings['coins']);
        $api_key = esc_html($settings['api-key']);

        $this->check_or_create_table();
        $result = $this->coindog_select_data();

        if (!$result) {
            $tokens = $this->get_tokens_data($api_key, $coins);
            $this->get_tokens_ranking($api_key, $tokens, explode(',', $coins));

            // salve results
            $this->save_tokens($tokens);
        } else {
            if (time() - $result[0]["last_update"] < 30) {
                // get tokens
                $tokens = $this->retrieve_tokens();
            } else {
                $tokens = $this->get_tokens_data($api_key, $coins);
                $this->get_tokens_ranking($api_key, $tokens, explode(',', $coins));

                // salve results
                $this->save_tokens($tokens);
            }
        }


        return print_r($this->generate_table($tokens));
    }

    function retrieve_tokens()
    {
        global $wpdb;

        // Nome della tabella nel database
        $table_name = 'coindog_cache';

        // Query per selezionare tutti i dati dalla tabella
        $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);


        // Se il risultato non è vuoto, restituisci l'array
        if (!empty($results)) {
            $cryptos = [];

            // Iterare su ogni riga dei risultati e mappare i valori agli stessi nomi dei parametri originali
            foreach ($results as $row) {
                $cryptos[] = [
                    'name' => $row['name'],
                    'symbol' => $row['symbol'],
                    'thumb' => $row['thumb'],
                    'price_usd' => (float) $row['price_usd'],
                    'valore_eth' => (float) $row['valore_eth'],
                    'market_cap_usd' => (float) $row['market_cap_usd'],
                    'market_cap_eth' => (float) $row['market_cap_eth'],
                    'market_cap_rank' => (int) $row['market_cap_rank'],
                    'high_24h' => (float) $row['high_24h'],
                    'low_24h' => (float) $row['low_24h'],
                    'price_change_percentage_24h' => (float) $row['price_change_percentage_24h'],
                    'last_update' => (int) $row['last_update'],
                ];
            }

            return $cryptos; // Restituisce l'array di criptovalute
        }

        // Restituisci un array vuoto se non ci sono risultati
        return [];
    }

    function save_tokens($tokens)
    {
        global $wpdb;

        // Nome della tabella nel database
        $table_name = "coindog_cache";

        $wpdb->query("DELETE FROM $table_name");

        // Ciclo su ogni elemento dell'array
        foreach ($tokens as $crypto) {
            // Assicurarsi che ogni campo sia presente nell'array
            $name = $crypto['name'] ?? null;
            $symbol = $crypto['symbol'] ?? null;
            $thumb = $crypto['thumb'] ?? null;
            $price_usd = $crypto['price_usd'] ?? null;
            $valore_eth = $crypto['valore_eth'] ?? null;
            $market_cap_usd = $crypto['market_cap_usd'] ?? null;
            $market_cap_eth = $crypto['market_cap_eth'] ?? null;
            $market_cap_rank = $crypto['market_cap_rank'] ?? null;
            $high_24h = $crypto['high_24h'] ?? null;
            $low_24h = $crypto['low_24h'] ?? null;
            $price_change_percentage_24h = $crypto['price_change_percentage_24h'] ?? null;

            // Aggiungi il timestamp corrente come last_update
            $last_update = time();

            // Esegui l'inserimento nel database
            $wpdb->insert(
                $table_name,  // Nome della tabella
                [
                    'name' => $name,
                    'symbol' => $symbol,
                    'thumb' => $thumb,
                    'price_usd' => $price_usd,
                    'valore_eth' => $valore_eth,
                    'market_cap_usd' => $market_cap_usd,
                    'market_cap_eth' => $market_cap_eth,
                    'market_cap_rank' => $market_cap_rank,
                    'high_24h' => $high_24h,
                    'low_24h' => $low_24h,
                    'price_change_percentage_24h' => $price_change_percentage_24h,
                    'last_update' => $last_update,
                ],
                [
                    '%s',   // name (string)
                    '%s',   // symbol (string)
                    '%s',   // thumb (string)
                    '%s',   // price_usd (float)
                    '%s',   // valore_eth (float)
                    '%s',   // market_cap_usd (float)
                    '%s',   // market_cap_eth (float)
                    '%d',   // market_cap_rank (integer)
                    '%s',   // high_24h (float)
                    '%s',   // low_24h (float)
                    '%s',   // price_change_percentage_24h (float)
                    '%d'    // last_update (integer/timestamp)
                ]
            );
        }
    }

    function get_tokens_ranking($api_key, &$tokens, $symbols)
    {
        $page = 0;
        $rank = 1;
        $found = 0;

        while ($found < count($symbols) && $page <= 4) {

            $url = "https://min-api.cryptocompare.com/data/top/mktcapfull?limit=100&tsym=USD&page=" . $page;


            $data = $this->http_request($url, $api_key);
            foreach ($data["Data"] as $coin) {
                if (in_array($coin["CoinInfo"]["Name"], $symbols)) {
                    // found
                    foreach ($tokens as &$token) {
                        if ($token["symbol"] == $coin["CoinInfo"]["Name"]) {
                            // match
                            $token["market_cap_rank"] = $rank;
                            $token["name"] = $coin["CoinInfo"]["FullName"];
                            $found += 1;
                        }
                    }
                }

                $rank += 1;
                // echo $rank;
            }

            $page += 1;
        }
    }

    function get_tokens_data($api_key, $coins)
    {
        $url = "https://min-api.cryptocompare.com/data/pricemultifull?fsyms=" . $coins . "&tsyms=USD,ETH";

        $data = $this->http_request($url, $api_key);

        $tokens = array();
        $symbols = explode(',', $coins);

        foreach ($symbols as $symbol) {

            $result = [
                'name' => $data["RAW"][$symbol]["USD"]['FROMSYMBOL'],
                'symbol' => $data["RAW"][$symbol]["USD"]['FROMSYMBOL'],
                'thumb' => $data["RAW"][$symbol]["USD"]['IMAGEURL'],
                'price_usd' => $data["RAW"][$symbol]["USD"]['PRICE'],
                'valore_eth' => $data["RAW"][$symbol]["ETH"]['PRICE'],
                'market_cap_usd' => $data["RAW"][$symbol]['USD']['MKTCAP'],
                'market_cap_eth' => $data["RAW"][$symbol]['ETH']['MKTCAP'],
                'market_cap_rank' => "400+",
                'high_24h' => $data["RAW"][$symbol]['USD']['HIGH24HOUR'],
                'low_24h' => $data["RAW"][$symbol]['USD']['LOW24HOUR'],
                'price_change_percentage_24h' => $data["RAW"][$symbol]['USD']['CHANGEPCT24HOUR']
            ];

            array_push($tokens, $result);
        }

        return $tokens;
    }


    function check_or_create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE coindog_cache (
                    name VARCHAR(255) NOT NULL,
                    symbol VARCHAR(10) NOT NULL,
                    thumb VARCHAR(255),
                    price_usd DECIMAL(25,18),
                    valore_eth DECIMAL(25,18),
                    market_cap_usd DECIMAL(18,2),
                    market_cap_eth DECIMAL(18,2),
                    market_cap_rank INT,
                    high_24h DECIMAL(25,18),
                    low_24h DECIMAL(25,18),
                    price_change_percentage_24h DECIMAL(25,18),
                    last_update BIGINT
                ); $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


    function coindog_select_data()
    {
        global $wpdb;
        $table_name = 'coindog_cache';

        $query = "SELECT * FROM $table_name";
        $results = $wpdb->get_results($wpdb->prepare($query), ARRAY_A);

        if (!$results) {
            return false;
        }

        return $results;
    }

    /**
     * REAL PLUGIN
     */

    function generate_table_header()
    {
        $html = "<style>
        table, th, td {
            margin: auto;
            border: 1px solid #D1D5DB !important;
            border-collapse: collapse;
            font-size: 16px;
            color: #1e293b;
        }
    
        td {
            padding: 5px 20px;
        }
    
        th{
            font-weight: 500;
        }
    
        table {
            width: 100%;
        }
        </style>";
        $html .= "<table style='width:100%;'>";
        $html .= "<thead><tr style='border: 1px solid black;'>
        <th>Name</th>
        <th>Symbol</th>
        <th>Price</th>
        <th>Price Change 24h</th>
        <th>Market Cap</th>
        <th>Market Cap (ETH)</th>
        <th>Rank</th>
        <th>High 24h</th>
        <th>Low 24h</th>
        </tr></thead>";

        return $html;
    }

    function generate_table($tokens)
    {
        $html = $this->generate_table_header();

        foreach ($tokens as $token) {

            $percent_color = "red";
            $svg = '<svg fill="red" width="15" height="15" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"></path></svg>';

            $price_usd = troncaDecimali(number_format($token['price_usd'], $this->getDecimalCount($token['price_usd']), '.', ','));    
            $market_cap_usd = number_format($token['market_cap_usd'], $this->getDecimalCount($token['market_cap_usd']), '.', ',');
            $market_cap_eth = number_format($token['market_cap_eth'], $this->getDecimalCount($token['market_cap_eth']), '.', ',');
            $high_24h = troncaDecimali(number_format($token['high_24h'], $this->getDecimalCount($token['high_24h']), '.', ','));
            $low_24h = troncaDecimali(number_format($token['low_24h'], $this->getDecimalCount($token['low_24h']), '.', ','));



            $percentage = $token['price_change_percentage_24h'];
            if ($percentage > 0) {
                $percent_color = "green";
                $svg = '<svg fill="green" width="14" height="14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"> <path d="m7.247 4.86-4.796 5.481c-.566.647-.106 1.659.753 1.659h9.592a1 1 0 0 0 .753-1.659l-4.796-5.48a1 1 0 0 0-1.506 0z"></path> </svg>';
            }
            $percentage = abs($percentage);

            $html .= "<tr>
            <td>
                <div style=\"display: flex; gap: 5px; align-items: center; padding-right: 10px;\">
                    <img style=\"height: 30px\" src=https://www.cryptocompare.com$token[thumb]>$token[name]
                </div>
            </td>
            <td>$token[symbol]</td>
            <td>$$price_usd</td>
            <td style=\"color: $percent_color\">
                <div style=\"display: flex; gap: 5px; align-items: center;\">
                    $svg $percentage%
                </div>
            </td>
            <td>$$market_cap_usd</td>
            <td>$market_cap_eth ETH</td>
            <td>$token[market_cap_rank]</td>
            <td>$$high_24h</td>
            <td>$$low_24h</td>
    
            </tr>
            ";
        }

        $html .= "</table>";

        return $html;
    }



    function getDecimalCount($number)
    {
        $parts = explode('.', (string)$number);
        return isset($parts[1]) ? strlen($parts[1]) : 0;
    }

    function http_request($url, $api_key)
    {
        // Prepara gli argomenti per la richiesta HTTP, includendo l'header personalizzato
        $args = array(
            'headers' => array(
                'x-cg-demo-api-key' => $api_key
            )
        );


        // Esegui la richiesta HTTP GET con l'header personalizzato
        $response = wp_remote_get($url, $args);

        // Verifica se la richiesta ha avuto successo
        if (is_wp_error($response)) {
            return 'Errore nella richiesta API: ' . $response->get_error_message();
        }

        // Ottieni il corpo della risposta e decodifica il JSON
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return "Errore";
        }

        return $data;
    }
}

function troncaDecimali($numero) {
    // Convertiamo il numero in stringa
    $stringa = (string)$numero;
    
    // Separiamo la parte intera da quella decimale
    $parti = explode('.', $stringa);
    
    // Se non ci sono decimali, restituiamo il numero originale
    if (count($parti) === 1) {
        return $stringa;
    }
    
    $parteIntera = $parti[0];
    $parteDecimale = $parti[1];
    
    // Contiamo le cifre decimali significative (diverse da zero)
    $cifre = 0;
    $lunghezza = strlen($parteDecimale);
    $risultato = '';
    
    for ($i = 0; $i < $lunghezza; $i++) {
        $cifra = $parteDecimale[$i];
        $risultato .= $cifra;
        
        if ($cifra !== '0') {
            $cifre++;
        }
        
        if ($cifre === 4) {
            break;
        }
    }
    
    // Rimuoviamo eventuali zeri finali
    $risultato = rtrim($risultato, '0');
    
    // Ricomponiamo il numero
    return $parteIntera . ($risultato !== '' ? '.' . $risultato : '');
}