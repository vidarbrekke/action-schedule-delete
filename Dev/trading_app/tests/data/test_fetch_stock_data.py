import unittest
from unittest.mock import patch, MagicMock
import sys
import os

# Define the current directory
current_dir = os.path.dirname(os.path.abspath(__file__))
# Define the root directory (two levels up from current directory)
root_dir = os.path.dirname(os.path.dirname(current_dir))
# Define the data directory
data_dir = os.path.join(root_dir, 'data')

# Print the directories for debugging
print("Current directory:", current_dir)
print("Root directory:", root_dir)
print("Data directory:", data_dir)

# Append the 'data' directory path to sys.path
sys.path.append(data_dir)

# Print sys.path for debugging
print("sys.path:", sys.path)

# Now you can import fetch_stock_data
import fetch_stock_data

class TestFetchStockData(unittest.TestCase):
    # Your test methods here

    if __name__ == '__main__':
        unittest.main()
