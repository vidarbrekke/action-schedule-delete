# Inventory Sync Google Sheets Apps Script

Synchronise inventory quantities between a **source** sheet (your working inventory file) and a **Thrive-generated** bulk-update sheet with enhanced debugging and error handling.

---

## Features

* **Custom menu** – adds an `Inventory Sync` menu the moment the Thrive sheet is opened.
* **Copy Inventory** – copies *Consumer* and *Wholesale* quantity values from the source sheet into the Thrive sheet, matching on the `SKU` column.
* **Smart zero-value handling** – only updates cells with non-zero quantities, leaving zero values blank in the target sheet.
* **Multi-tab support** – correctly handles URLs with specific tab IDs (gid parameters) for both source and target sheets.
* **Comprehensive debugging tools** – multiple inspection functions to troubleshoot issues.
* **Enhanced error handling** – detailed logging and validation with clear error messages.
* **Activity log** – writes a detailed summary to **Inventory Sync Log** with match statistics and execution details.

---

## Required column headers

These default header names are case-sensitive.

| Location | Purpose | Header text |
|----------|---------|-------------|
| Source sheet | Primary key | `SKU` |
| Source sheet | Consumer quantity | `CONSUMER` |
| Source sheet | Wholesale quantity | `WHOLESALE` |
| Source sheet | Flag that inventory should be removed | `REMOVE` |
| Thrive sheet | Primary key | `SKU` |
| Thrive sheet | Consumer quantity | `Motherknitter.com Quantity Received` |
| Thrive sheet | Wholesale quantity | `Wholesale Website Quantity Received` |

If your sheets use different header names simply edit the constants at the top of `inventory_sync.gs`.

---

## Installation (first-time)

1. Open the Thrive-generated **Google Sheet** you want to populate.
2. Click **Extensions ➜ Apps Script**.
3. Delete any code block that is already present and **create a new script file** called `inventory_sync.gs`.
4. Paste the contents of `inventory_sync.gs` (found in this repository) into the new file.
5. Press **Save (⌘S)**.
6. Back in the spreadsheet, reload the page. You should now see an **Inventory Sync** menu next to `Help`.

> **Authorisation** – the first time you run either command Google will ask for permission to run the script under your account. Click *Review permissions* and follow the prompts.

---

## Usage

### 1 · Copy Inventory

1. Go to **Inventory Sync ➜ Copy Inventory**.
2. When prompted, paste the **URL of your source sheet** (including the specific tab if needed - URLs with `gid=` parameters are fully supported).
3. When prompted, paste the **URL of your target sheet**.
4. Wait for the confirmation dialogue – detailed results will be listed in **Inventory Sync Log**.

**Note**: Only non-zero quantities will be written to the target sheet. Zero values will leave cells blank or unchanged.

### 2 · Remove Inventory

1. Mark rows in your **source sheet** with any truthy value under the `REMOVE` column – e.g. `Y`, `true`, `1`, etc.
2. In the Thrive sheet go to **Inventory Sync ➜ Remove Inventory** and again paste the source-sheet URL.
3. Quantities for matching SKUs will be set to `0`. Review the **Inventory Sync Log** tab for a summary.

---

## Debugging & Troubleshooting

The script includes several diagnostic tools accessible from the **Inventory Sync** menu:

### Debug Script
Basic functionality test - verifies script permissions and configuration.

### Inspect Target Headers  
Shows exactly what column headers are found in your target sheet and verifies they match the expected names.

### Inspect Source Headers
Shows exactly what column headers are found in your source sheet and displays sample data.

### Analyze Match Statistics
Provides detailed analysis of matches between source and target sheets, including:
- Total SKU counts in each sheet
- Match percentage and count
- Number of non-zero quantity updates
- Unmatched SKUs in both directions
- Warnings about potential data limits

### Viewing Debug Output
To see detailed logs:
1. Go to **Extensions ➜ Apps Script**  
2. Select **View ➜ Execution transcript**
3. Run any function to see comprehensive logging

---

## Customisation

Open `inventory_sync.gs` and adjust the `CONFIG` object if your sheet uses different header names.

```js
var CONFIG = {
  source: {
    sku: 'SKU',
    consumerQty: 'CONSUMER',
    wholesaleQty: 'WHOLESALE',
    headerRow: 1, // The 1-based row number for the header in the Source sheet
  },
  target: {
    sku: 'SKU',
    consumerQty: 'Motherknitter.com Quantity Received',
    wholesaleQty: 'Wholesale Website Quantity Received',
    headerRow: 2, // The 1-based row number for the header in the Target (Thrive) sheet
  },
  logSheetName: 'Inventory Sync Log',
};
```

---

## Common Issues & Solutions

### "Could not find required column(s)"
- Use **Inspect Target Headers** or **Inspect Source Headers** to verify column names
- Check that `headerRow` configuration matches your sheet structure
- Ensure column names match exactly (case-sensitive)

### "Source map built with 0 SKUs"  
- Verify the source URL points to the correct tab
- Use **Inspect Source Headers** to check data structure
- Ensure source sheet has data below the header row

### Multi-tab Spreadsheets
- Always use the full URL including the `gid=` parameter when copying from specific tabs
- The script will automatically detect and open the correct tab
- If no `gid` is specified, the first tab will be used

### Zero Values
- The script only updates cells with positive quantities from the source
- Zero values in source data will leave target cells unchanged (blank)
- Use **Analyze Match Statistics** to see how many cells will actually be updated

---

## Technical Notes

* Written in **Google Apps Script** (ES5 JavaScript) for maximum compatibility
* Enhanced error handling with comprehensive logging and validation
* Supports multi-tab spreadsheets via URL `gid` parameter parsing
* Uses efficient batch operations with `setValues()` for performance
* Automatically handles inconsistent log data structures
* Includes data validation to prevent common Google Sheets API errors
* Provides detailed execution tracking and match analysis

---

## Version History

**Latest**: Enhanced version with comprehensive debugging, multi-tab support, zero-value handling, and robust error handling.

**Features added**:
- Multi-tab URL support with `gid` parameter parsing
- Zero-value filtering (only non-zero quantities are written)
- Comprehensive debugging and inspection tools
- Enhanced error handling and validation
- Detailed match statistics and analysis
- Improved logging with execution tracking

Feel free to open a PR if you spot improvements or encounter edge cases. 