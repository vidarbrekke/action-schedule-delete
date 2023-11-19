# common.py

def identify_market_regime_enhanced(data):
    """
    Enhanced function to identify market regimes based on SMA.
    """
    short_sma = data['close'].rolling(window=20).mean()
    long_sma = data['close'].rolling(window=50).mean()

    data['regime'] = 'neutral'
    data.loc[short_sma > long_sma, 'regime'] = 'bull_market'
    data.loc[short_sma < long_sma, 'regime'] = 'bear_market'

    return data

def adjust_risk_parameters(strategy_params, market_regime):
    """
    Adjusts risk parameters based on the identified market regime.
    """
    adjusted_params = strategy_params.copy()
    if market_regime == 'bull_market':
        adjusted_params['stop_loss_percent'] *= 0.8
        adjusted_params['take_profit_percent'] *= 1.2
    elif market_regime == 'bear_market':
        adjusted_params['stop_loss_percent'] *= 1.2
        adjusted_params['take_profit_percent'] *= 0.8
    # No adjustments for 'neutral' regime
    return adjusted_params
