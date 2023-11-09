import sqlite3

# Define the connection to the SQLite database
conn = sqlite3.connect('data/stock_data.db')

# Create a cursor object using the cursor() method
cursor = conn.cursor()

# Create table - STOCK_DATA
cursor.execute('''CREATE TABLE STOCK_DATA
         (DATE TEXT NOT NULL,
          TICKER TEXT NOT NULL,
          OPEN REAL,
          HIGH REAL,
          LOW REAL,
          CLOSE REAL,
          ADJ_CLOSE REAL,
          VOLUME INTEGER,
          PRIMARY KEY (DATE, TICKER))''')

# Commit your changes in the database
conn.commit()

# Close the connection
conn.close()

print("Database and stock data table created successfully.")
