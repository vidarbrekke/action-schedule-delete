import sys
import os

# Define the root directory
root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
# Append the root directory path to sys.path
sys.path.append(root_dir)

from data.fetch_stock_data import fetch_data
from analysis.indicators import add_indicators

# Fetch historical data for AMD
symbol = 'AMD'
start_date = '2023-01-01'
end_date = '2023-04-01'
data = fetch_data(symbol, start_date, end_date)

# Apply indicators
data_with_indicators = add_indicators(data)
print(data_with_indicators.head())  # Display the first few rows to verify
