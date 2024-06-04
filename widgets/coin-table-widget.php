<?php
class Coin_Table_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'coin_table_widget';
    }

    public function get_title() {
        return 'Coin Table';
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function register_controls() {
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
            'is-demo',
			[
				'type' => \Elementor\Controls_Manager::SELECT,
				'label' => esc_html__( 'Is Demo', 'textdomain' ),
				'options' => [
					'yes' => esc_html__( 'Yes', 'textdomain' ),
					'no' => esc_html__( 'No', 'textdomain' ),
				],
				'default' => 'yes',
			]
        );

        $this->add_control(
            'coins',
            [
                'label' => 'Coins to list',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'dogecoin',
                'placeholder' => 'Enter coins tag separated with comma',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $coins = explode(',', esc_html($settings['coins']));
        $is_demo = esc_html($settings['is-demo']);
        $api_key = esc_html($settings['api-key']);

        $token_info = [];
        foreach ($coins as $id) {
            if (!empty($id))
                array_push($token_info, $this->get_token_info($api_key, $is_demo, $id));
        }
    
        return print_r($this->generate_table($token_info));

        // Example static output (In practice, fetch data from an API)


    }


    /**
     * REAL PLUGIN
     */
    
    function generate_table_header(){
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
    
    function generate_table($tokens) {
        $html = $this->generate_table_header();

        foreach($tokens as $token){
    
            $percent_color = "red";
            $svg = '<svg fill="red" width="15" height="15" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"></path></svg>';
            
            $price_usd = number_format($token['price_usd'], $this->getDecimalCount($token['price_usd']), '.', ',');
            $market_cap_usd = number_format($token['market_cap_usd'], $this->getDecimalCount($token['market_cap_usd']), '.', ',');
            $market_cap_eth = number_format($token['market_cap_eth'], $this->getDecimalCount($token['market_cap_eth']), '.', ',');
            $high_24h = number_format($token['high_24h'], $this->getDecimalCount($token['high_24h']), '.', ',');
            $low_24h = number_format($token['low_24h'], $this->getDecimalCount($token['low_24h']), '.', ',');



            $percentage = $token['price_change_percentage_24h'];
            if ($percentage>0){
                $percent_color = "green";
                $svg = '<svg fill="green" width="14" height="14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"> <path d="m7.247 4.86-4.796 5.481c-.566.647-.106 1.659.753 1.659h9.592a1 1 0 0 0 .753-1.659l-4.796-5.48a1 1 0 0 0-1.506 0z"></path> </svg>';
            }
            $percentage = abs($percentage);
    
            $html .= "<tr>
            <td>
                <div style=\"display: flex; gap: 5px; align-items: center; padding-right: 10px;\">
                    <img src=$token[thumb]/>$token[name]
                </div>
            </td>
            <td>$token[symbol]</td>
            <td>$$price_usd</td>
            <td style=\"color: $percent_color\">
                <div style=\"display: flex; gap: 5px; align-items: center;\">
                    $svg $percentage%
                </div>
            </td>
            <td>$market_cap_usd</td>
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
    
    
    function get_token_info($api_key, $is_demo, $coin_id){
        $url = "https://api.coingecko.com/api/v3/coins/" . $coin_id;
    
        // Prepara gli argomenti per la richiesta HTTP, includendo l'header personalizzato
        if ($is_demo){
            $args = array(
                'headers' => array(
                    'x-cg-demo-api-key' => $api_key
                )
            );    
        } else {
            $args = array(
                'headers' => array(
                    'x-cg-pro-api-key' => $api_key
                )
            );  
        }
    
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
            return 'Nessun dato disponibile per il token specificato.';
        }
    
        // Estrai i dati necessari
        $result = [
            'name' => $data['name'],
            'symbol' => $data['symbol'],
            'thumb' => $data['image']['thumb'],
            'price_usd' => $data['market_data']['current_price']['usd'],
            'valore_eth' => $data['market_data']['current_price']['eth'],
            'market_cap_usd' => $data['market_data']['market_cap']['usd'],
            'market_cap_eth' => $data['market_data']['market_cap']['eth'],
            'market_cap_rank' => $data['market_cap_rank'],
            'high_24h' => $data['market_data']['high_24h']['usd'],
            'low_24h' => $data['market_data']['low_24h']['usd'],
            'price_change_percentage_24h' => $data['market_data']['price_change_percentage_24h']
        ];
    
        return $result;
    
    }

    function getDecimalCount($number) {
        $parts = explode('.', (string)$number);
        return isset($parts[1]) ? strlen($parts[1]) : 0;
    }
}