# Trading Metrics Guide

This document provides detailed explanations of the various trading metrics used in the application. Understanding these metrics is crucial for analyzing the performance of trading strategies.

## Metrics Overview

### Final Balance
- **Description**: Represents the ending cash balance after executing the trading strategy over a specified period.
- **Interpretation**: A final balance higher than the initial balance indicates a profitable strategy. Conversely, a lower final balance suggests a loss.

### Total Return
- **Description**: The percentage change in the initial balance over the trading period.
- **Interpretation**: Positive values indicate a gain, while negative values indicate a loss. Higher percentages represent more effective strategies.

### Win Rate
- **Description**: The ratio of winning trades to the total number of trades.
- **Interpretation**: Expressed as a percentage. A higher win rate suggests a more reliable strategy. However, it doesn't account for the magnitude of wins or losses.

### Profit Factor
- **Description**: The ratio of the total profits from winning trades to the total losses from losing trades.
- **Interpretation**: Values greater than 1 indicate a strategy that gains more than it loses. The higher the value, the better the strategy's profitability.

### Expectancy
- **Description**: The average amount gained or lost per trade.
- **Interpretation**: Positive expectancy indicates a strategy that, on average, wins more than it loses per trade. Negative expectancy suggests the opposite.

### Sharpe Ratio (To be implemented)
- **Description**: Measures the risk-adjusted return of a trading strategy.
- **Interpretation**: A higher Sharpe ratio indicates a more attractive risk-adjusted return. Typically, a ratio greater than 1 is considered good.

### Sortino Ratio (To be implemented)
- **Description**: Similar to the Sharpe ratio but focuses only on downside volatility.
- **Interpretation**: Higher values are better, indicating that the strategy earns more return per unit of bad risk.

## Additional Information

- Each metric provides a unique lens through which the strategy's performance can be assessed.
- It's essential to consider these metrics collectively rather than in isolation to get a comprehensive view of the strategy's effectiveness.
- Market conditions, the time frame of the strategy, and other external factors can significantly influence these metrics.
