import sqlite3
import os

def get_column_names():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    database_path = os.path.join(script_dir, "trading_app.db")

    # Connect to the SQLite database
    conn = sqlite3.connect(database_path)
    cursor = conn.cursor()

    # Get column names from 'prices' table
    cursor.execute("PRAGMA table_info(prices);")
    columns = cursor.fetchall()

    # Close the database connection
    conn.close()

    # Extracting and returning column names
    return [col[1] for col in columns]

if __name__ == "__main__":
    column_names = get_column_names()
    print("Column names in 'prices' table:")
    for name in column_names:
        print(name)
