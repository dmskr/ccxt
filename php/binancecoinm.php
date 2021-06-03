<?php

namespace ccxt;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\BadRequest;

class binancecoinm extends binance {

    public function describe() {
        return $this->deep_extend(parent::describe (), array(
            'id' => 'binancecoinm',
            'name' => 'Binance COIN-M',
            'urls' => array(
                'logo' => 'https://user-images.githubusercontent.com/1294454/117738721-668c8d80-b205-11eb-8c49-3fad84c4a07f.jpg',
            ),
            'options' => array(
                'defaultType' => 'delivery',
                'leverageBrackets' => null,
            ),
            'has' => array(
                'fetchPositions' => true,
                'fetchIsolatedPositions' => true,
                'fetchFundingRate' => true,
                'fetchFundingHistory' => true,
                'setLeverage' => true,
                'setMode' => true,
            ),
            // https://www.binance.com/en/fee/deliveryFee
            'fees' => array(
                'trading' => array(
                    'tierBased' => true,
                    'percentage' => true,
                    'taker' => $this->parse_number('0.000500'),
                    'maker' => $this->parse_number('0.000100'),
                    'tiers' => array(
                        'taker' => array(
                            array( $this->parse_number('0'), $this->parse_number('0.000500') ),
                            array( $this->parse_number('250'), $this->parse_number('0.000450') ),
                            array( $this->parse_number('2500'), $this->parse_number('0.000400') ),
                            array( $this->parse_number('7500'), $this->parse_number('0.000300') ),
                            array( $this->parse_number('22500'), $this->parse_number('0.000250') ),
                            array( $this->parse_number('50000'), $this->parse_number('0.000240') ),
                            array( $this->parse_number('100000'), $this->parse_number('0.000240') ),
                            array( $this->parse_number('200000'), $this->parse_number('0.000240') ),
                            array( $this->parse_number('400000'), $this->parse_number('0.000240') ),
                            array( $this->parse_number('750000'), $this->parse_number('0.000240') ),
                        ),
                        'maker' => array(
                            array( $this->parse_number('0'), $this->parse_number('0.000100') ),
                            array( $this->parse_number('250'), $this->parse_number('0.000080') ),
                            array( $this->parse_number('2500'), $this->parse_number('0.000050') ),
                            array( $this->parse_number('7500'), $this->parse_number('0.0000030') ),
                            array( $this->parse_number('22500'), $this->parse_number('0') ),
                            array( $this->parse_number('50000'), $this->parse_number('-0.000050') ),
                            array( $this->parse_number('100000'), $this->parse_number('-0.000060') ),
                            array( $this->parse_number('200000'), $this->parse_number('-0.000070') ),
                            array( $this->parse_number('400000'), $this->parse_number('-0.000080') ),
                            array( $this->parse_number('750000'), $this->parse_number('-0.000090') ),
                        ),
                    ),
                ),
            ),
        ));
    }

    public function fetch_trading_fees($params = array ()) {
        $this->load_markets();
        $marketSymbols = is_array($this->markets) ? array_keys($this->markets) : array();
        $fees = array();
        $accountInfo = $this->dapiPrivateGetAccount ($params);
        //
        // {
        //      "canDeposit" => true,
        //      "canTrade" => true,
        //      "canWithdraw" => true,
        //      "$feeTier" => 2,
        //      "updateTime" => 0
        //      ...
        //  }
        //
        $feeTier = $this->safe_integer($accountInfo, 'feeTier');
        $feeTiers = $this->fees['trading']['tiers'];
        $maker = $feeTiers['maker'][$feeTier][1];
        $taker = $feeTiers['taker'][$feeTier][1];
        for ($i = 0; $i < count($marketSymbols); $i++) {
            $symbol = $marketSymbols[$i];
            $fees[$symbol] = array(
                'info' => array(
                    'feeTier' => $feeTier,
                ),
                'symbol' => $symbol,
                'maker' => $maker,
                'taker' => $taker,
            );
        }
        return $fees;
    }

    public function transfer_in($code, $amount, $params = array ()) {
        // transfer from spot wallet to coinm futures wallet
        return $this->futuresTransfer ($code, $amount, 3, $params);
    }

    public function transfer_out($code, $amount, $params = array ()) {
        // transfer from coinm futures wallet to spot wallet
        return $this->futuresTransfer ($code, $amount, 4, $params);
    }

    public function fetch_funding_rate($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market($symbol);
        $request = array(
            'symbol' => $market['id'],
        );
        $response = $this->dapiPublicGetPremiumIndex (array_merge($request, $params));
        //
        //     array(
        //       {
        //         "$symbol" => "ETHUSD_PERP",
        //         "pair" => "ETHUSD",
        //         "markPrice" => "2452.47558343",
        //         "indexPrice" => "2454.04584679",
        //         "estimatedSettlePrice" => "2464.80622965",
        //         "lastFundingRate" => "0.00004409",
        //         "interestRate" => "0.00010000",
        //         "nextFundingTime" => "1621900800000",
        //         "time" => "1621875158012"
        //       }
        //     )
        //
        return $this->parse_funding_rate ($response[0]);
    }

    public function fetch_funding_rates($symbols = null, $params = array ()) {
        $this->load_markets();
        $response = $this->dapiPublicGetPremiumIndex ($params);
        $result = array();
        for ($i = 0; $i < count($response); $i++) {
            $entry = $response[$i];
            $parsed = $this->parse_funding_rate ($entry);
            $result[] = $parsed;
        }
        return $this->filter_by_array($result, 'symbol', $symbols);
    }

    public function load_leverage_brackets($reload = false, $params = array ()) {
        $this->load_markets();
        // by default cache the leverage $bracket
        // it contains useful stuff like the maintenance margin and initial margin for positions
        if (($this->options['leverageBrackets'] === null) || ($reload)) {
            $response = $this->dapiPrivateV2GetLeverageBracket ($params);
            $this->options['leverageBrackets'] = array();
            for ($i = 0; $i < count($response); $i++) {
                $entry = $response[$i];
                $marketId = $this->safe_string($entry, 'symbol');
                $symbol = $this->safe_symbol($marketId);
                $brackets = $this->safe_value($entry, 'brackets');
                $result = array();
                for ($j = 0; $j < count($brackets); $j++) {
                    $bracket = $brackets[$j];
                    // we use floats here internally on purpose
                    $qtyFloor = $this->safe_float($bracket, 'qtyFloor');
                    $maintenanceMarginPercentage = $this->safe_string($bracket, 'maintMarginRatio');
                    $result[] = array( $qtyFloor, $maintenanceMarginPercentage );
                }
                $this->options['leverageBrackets'][$symbol] = $result;
            }
        }
        return $this->options['leverageBrackets'];
    }

    public function fetch_positions($symbols = null, $params = array ()) {
        $this->load_markets();
        $this->load_leverage_brackets();
        $account = $this->dapiPrivateGetAccount ($params);
        $result = $this->parse_account_positions ($account);
        return $this->filter_by_array($result, 'symbol', $symbols, false);
    }

    public function fetch_isolated_positions($symbol = null, $params = array ()) {
        // only supported in usdm futures
        $this->load_markets();
        $this->load_leverage_brackets();
        $request = array();
        $market = null;
        if ($symbol !== null) {
            $market = $this->market($symbol);
            $request['symbol'] = $market['id'];
        }
        $response = $this->dapiPrivateGetPositionRisk (array_merge($request, $params));
        if ($symbol === null) {
            $result = array();
            for ($i = 0; $i < count($response); $i++) {
                $parsed = $this->parse_position_risk ($response[$i], $market);
                if ($parsed['marginType'] === 'isolated') {
                    $result[] = $parsed;
                }
            }
            return $result;
        } else {
            return $this->parse_position_risk ($this->safe_value($response, 0), $market);
        }
    }

    public function fetch_funding_history($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = null;
        // "TRANSFER"，"WELCOME_BONUS", "REALIZED_PNL"，"FUNDING_FEE", "COMMISSION" and "INSURANCE_CLEAR"
        $request = array(
            'incomeType' => 'FUNDING_FEE',
        );
        if ($symbol !== null) {
            $market = $this->market($symbol);
            $request['symbol'] = $market['id'];
        }
        if ($since !== null) {
            $request['startTime'] = $since;
        }
        if ($limit !== null) {
            $request['limit'] = $limit;
        }
        $response = $this->dapiPrivateGetIncome (array_merge($request, $params));
        return $this->parse_incomes ($response, $market, $since, $limit);
    }

    public function set_leverage($symbol, $leverage, $params = array ()) {
        // WARNING => THIS WILL INCREASE LIQUIDATION PRICE FOR OPEN ISOLATED LONG POSITIONS
        // AND DECREASE LIQUIDATION PRICE FOR OPEN ISOLATED SHORT POSITIONS
        if (($leverage < 1) || ($leverage > 125)) {
            throw new BadRequest($this->id . ' $leverage should be between 1 and 125');
        }
        $this->load_markets();
        $market = $this->market($symbol);
        $request = array(
            'symbol' => $market['id'],
            'leverage' => $leverage,
        );
        return $this->dapiPrivatePostLeverage (array_merge($request, $params));
    }

    public function set_mode($symbol, $marginType, $params = array ()) {
        //
        // array( "code" => -4048 , "msg" => "Margin type cannot be changed if there exists position." )
        //
        // or
        //
        // array( "code" => 200, "msg" => "success" )
        //
        $marginType = strtoupper($marginType);
        if (($marginType !== 'ISOLATED') && ($marginType !== 'CROSSED')) {
            throw new BadRequest($this->id . ' $marginType must be either isolated or crossed');
        }
        $this->load_markets();
        $market = $this->market($symbol);
        $request = array(
            'symbol' => $market['id'],
            'marginType' => $marginType,
        );
        return $this->dapiPrivatePostMarginType (array_merge($request, $params));
    }
}
