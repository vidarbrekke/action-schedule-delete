/**
 * Inventory Sync – Google Apps Script
 * -----------------------------------
 * Copy or clear inventory quantities in a Thrive-generated sheet
 * based on data in a separate source sheet.
 *
 * See README.md for usage instructions.
 */

/* ============================= CONFIGURATION ============================= */

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

/* =========================== MENU INITIALISER ============================ */

/**
 * Adds the custom menu every time the spreadsheet is opened.
 */
function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('Inventory Sync')
    .addItem('Copy Inventory', 'copyInventory')
    .addSeparator()
    .addItem('Debug Script', 'debugScript')
    .addItem('Inspect Target Headers', 'inspectTargetHeaders')
    .addItem('Inspect Source Headers', 'inspectSourceHeaders')
    .addItem('Analyze Match Statistics', 'analyzeMatchStatistics')
    .addToUi();
}

/* ============================ UI UTILITIES ============================== */

/**
 * Prompts the user for a source-sheet URL and returns it.
 * @throws {Error} if the user cancels the prompt.
 */
function promptForSourceUrl() {
  var ui = SpreadsheetApp.getUi();
  var resp = ui.prompt('Source Sheet URL', 'Paste the URL of your source inventory sheet:', ui.ButtonSet.OK_CANCEL);
  if (resp.getSelectedButton() !== ui.Button.OK) {
    throw new Error('Operation cancelled by user');
  }
  return resp.getResponseText().trim();
}

/**
 * Prompts the user for a target-sheet URL and returns it.
 * @throws {Error} if the user cancels the prompt.
 */
function promptForTargetUrl() {
  var ui = SpreadsheetApp.getUi();
  var resp = ui.prompt('Target Sheet URL', 'Paste the URL of the Thrive-generated sheet to update:', ui.ButtonSet.OK_CANCEL);
  if (resp.getSelectedButton() !== ui.Button.OK) {
    throw new Error('Operation cancelled by user');
  }
  return resp.getResponseText().trim();
}

/* =========================== DATA HELPERS =============================== */

/**
 * Extracts headers and data from a sheet, given a specific 1-based header row number.
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet The sheet to read from.
 * @param {number} headerRow The 1-based row number where the headers are located.
 * @return {{headers: Array<string>, data: Array<Array>}} An object containing the headers and the data rows.
 */
function getSheetData(sheet, headerRow) {
  // Ensure headerRow is at least 1
  headerRow = Math.max(1, headerRow || 1);
  
  var maxCols = sheet.getMaxColumns();
  var headers = sheet.getRange(headerRow, 1, 1, maxCols).getValues()[0];

  var lastRow = sheet.getLastRow();
  var data = [];
  if (lastRow > headerRow) {
    data = sheet.getRange(headerRow + 1, 1, lastRow - headerRow, maxCols).getValues();
  }
  
  return { headers: headers, data: data };
}

/**
 * Opens the correct sheet from the URL, respecting the gid parameter if present.
 * If no gid is specified, opens the first sheet.
 * @param {string} url
 * @return {GoogleAppsScript.Spreadsheet.Sheet}
 */
function openFirstSheet(url) {
  var spreadsheet = SpreadsheetApp.openByUrl(url);
  
  // Extract gid from URL if present
  var gidMatch = url.match(/[#&]gid=([0-9]+)/);
  if (gidMatch) {
    var gid = gidMatch[1];
    console.log('Found gid in URL:', gid);
    
    // Find sheet by gid
    var sheets = spreadsheet.getSheets();
    for (var i = 0; i < sheets.length; i++) {
      if (sheets[i].getSheetId().toString() === gid) {
        console.log('Opening sheet by gid:', sheets[i].getName());
        return sheets[i];
      }
    }
    
    console.log('Warning: Could not find sheet with gid ' + gid + ', falling back to first sheet');
  }
  
  // Fall back to first sheet if no gid or gid not found
  var firstSheet = spreadsheet.getSheets()[0];
  console.log('Opening first sheet:', firstSheet.getName());
  return firstSheet;
}

/**
 * Builds a header-name → column-index map for fast lookups.
 * @param {Array<string>} headers
 */
function buildHeaderIndex(headers) {
  var idx = {};
  headers.forEach(function (h, i) { idx[h] = i; });
  return idx;
}

/**
 * Builds a map keyed by SKU with the following shape:
 * {
 *   <SKU>: {
 *     consumerQty: Number,
 *     wholesaleQty: Number,
 *   },
 *   ...
 * }
 *
 * Missing quantity cells default to 0.
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet Source sheet
 */
function buildSourceMap(sheet) {
  console.log('=== BUILDING SOURCE MAP ===');
  console.log('Source sheet name:', sheet.getName());
  console.log('Reading source headers from row:', CONFIG.source.headerRow);
  
  var sheetInfo = getSheetData(sheet, CONFIG.source.headerRow);
  var data = sheetInfo.data;
  var headers = sheetInfo.headers;
  
  console.log('Source sheet headers:', JSON.stringify(headers));
  console.log('Source sheet data rows:', data.length);
  console.log('Looking for source columns:', {
    sku: CONFIG.source.sku,
    consumer: CONFIG.source.consumerQty,
    wholesale: CONFIG.source.wholesaleQty
  });
  
  var idx = buildHeaderIndex(headers);
  console.log('Source header indices:', {
    sku: idx[CONFIG.source.sku],
    consumer: idx[CONFIG.source.consumerQty],
    wholesale: idx[CONFIG.source.wholesaleQty]
  });

  // Check if required headers are missing
  var missingSourceHeaders = [];
  if (idx[CONFIG.source.sku] === undefined) missingSourceHeaders.push(CONFIG.source.sku);
  if (idx[CONFIG.source.consumerQty] === undefined) missingSourceHeaders.push(CONFIG.source.consumerQty);
  if (idx[CONFIG.source.wholesaleQty] === undefined) missingSourceHeaders.push(CONFIG.source.wholesaleQty);
  
  if (missingSourceHeaders.length > 0) {
    console.error('Missing source headers:', missingSourceHeaders);
    throw new Error('Could not find required column(s) in the Source sheet: "' + missingSourceHeaders.join('", "') + '". Found headers: ' + JSON.stringify(headers));
  }

  var map = {};
  var validRows = 0;
  data.forEach(function (row, i) {
    // Normalise SKU: trim whitespace and ensure it's a string
    var sku = String(row[idx[CONFIG.source.sku]] || '').trim();
    if (!sku) {
      if (i < 5) console.log('Row ' + (i + 1) + ': Empty SKU, skipping');
      return; // skip blank rows
    }

    validRows++;
    if (validRows <= 3) {
      console.log('Row ' + (i + 1) + ' - SKU: "' + sku + '", Consumer: ' + (row[idx[CONFIG.source.consumerQty]] || 0) + ', Wholesale: ' + (row[idx[CONFIG.source.wholesaleQty]] || 0));
    }

    map[sku] = {
      consumerQty: row[idx[CONFIG.source.consumerQty]] || 0,
      wholesaleQty: row[idx[CONFIG.source.wholesaleQty]] || 0,
    };
  });
  
  console.log('Total valid SKUs found in source:', validRows);
  return map;
}

/**
 * Writes a log table (2-D array) to the log sheet, overwriting any existing
 * content. If the sheet does not exist it will be created.
 * @param {Array<Array>} rows 2-D array where rows[0] is the header row.
 */
function writeLog(rows) {
  console.log('writeLog called with', rows.length, 'rows');
  
  try {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(CONFIG.logSheetName);
    if (!sheet) {
      console.log('Creating new log sheet:', CONFIG.logSheetName);
      sheet = ss.insertSheet(CONFIG.logSheetName);
    } else {
      console.log('Clearing existing log sheet');
      sheet.clear();
    }

    if (rows && rows.length) {
      // Validate data structure before writing
      console.log('Validating log data structure...');
      
      // Check for inconsistent row lengths
      var maxCols = 0;
      var minCols = Infinity;
      rows.forEach(function(row, i) {
        if (!Array.isArray(row)) {
          throw new Error('Log row ' + i + ' is not an array: ' + JSON.stringify(row));
        }
        maxCols = Math.max(maxCols, row.length);
        minCols = Math.min(minCols, row.length);
      });
      
      console.log('Log data validation - rows:', rows.length, 'maxCols:', maxCols, 'minCols:', minCols);
      
      if (maxCols !== minCols) {
        console.log('Warning: Inconsistent row lengths detected, normalizing...');
        // Normalize all rows to have the same length
        rows = rows.map(function(row) {
          var normalizedRow = row.slice(); // copy the row
          while (normalizedRow.length < maxCols) {
            normalizedRow.push(''); // pad with empty strings
          }
          return normalizedRow;
        });
      }
      
      console.log('Writing log data to range: 1,1,' + rows.length + ',' + maxCols);
      sheet.getRange(1, 1, rows.length, maxCols).setValues(rows);
      console.log('Log data written successfully');
    } else {
      console.log('No log data to write');
    }
  } catch (logWriteError) {
    console.error('Error in writeLog function:', logWriteError.toString());
    throw logWriteError;
  }
}

/* ============================= CORE LOGIC =============================== */

/**
 * Copies inventory counts from the source sheet into the active Thrive sheet.
 * @param {string} srcUrl
 */
function runCopyJob(srcUrl, targetUrl) {
  var ui = SpreadsheetApp.getUi();
  try {
    console.log('Starting runCopyJob with source URL:', srcUrl);
    console.log('Starting runCopyJob with target URL:', targetUrl);
    
    var sourceMap = buildSourceMap(openFirstSheet(srcUrl));
    console.log('Source map built with', Object.keys(sourceMap).length, 'SKUs');
    console.log('Sample source data:', JSON.stringify(Object.keys(sourceMap).slice(0, 3).reduce(function(obj, key) {
      obj[key] = sourceMap[key];
      return obj;
    }, {})));
    
    var targetSheet = openFirstSheet(targetUrl);
    console.log('Target sheet name:', targetSheet.getName());
    console.log('Target sheet URL:', targetSheet.getParent().getUrl());

    var sheetInfo = getSheetData(targetSheet, CONFIG.target.headerRow);
    var data = sheetInfo.data;
    var headers = sheetInfo.headers;
    var idx = buildHeaderIndex(headers);
    
    console.log('Target sheet headers (all):', JSON.stringify(headers));
    console.log('Target sheet has', data.length, 'data rows');
    console.log('Looking for these exact headers:', {
      sku: CONFIG.target.sku,
      consumer: CONFIG.target.consumerQty,
      wholesale: CONFIG.target.wholesaleQty
    });
    console.log('Header indices found:', {
      sku: idx[CONFIG.target.sku],
      consumer: idx[CONFIG.target.consumerQty],
      wholesale: idx[CONFIG.target.wholesaleQty]
    });

    // Show similar headers to help identify the issue
    var expectedHeaders = [CONFIG.target.sku, CONFIG.target.consumerQty, CONFIG.target.wholesaleQty];
    expectedHeaders.forEach(function(expected) {
      console.log('Looking for: "' + expected + '"');
      var similar = headers.filter(function(h) {
        return h && h.toString().toLowerCase().indexOf(expected.toLowerCase().substring(0, 10)) >= 0;
      });
      if (similar.length > 0) {
        console.log('  Similar headers found:', JSON.stringify(similar));
      }
    });

    // Validate that all required headers were found in the target sheet
    var missingHeaders = [];
    if (idx[CONFIG.target.sku] === undefined) missingHeaders.push(CONFIG.target.sku);
    if (idx[CONFIG.target.consumerQty] === undefined) missingHeaders.push(CONFIG.target.consumerQty);
    if (idx[CONFIG.target.wholesaleQty] === undefined) missingHeaders.push(CONFIG.target.wholesaleQty);

    if (missingHeaders.length > 0) {
      console.log('=== HEADER DEBUGGING ===');
      console.log('Expected headers:', JSON.stringify(expectedHeaders));
      console.log('Actual headers:', JSON.stringify(headers));
      console.log('Headers reading from row:', CONFIG.target.headerRow);
      throw new Error('Could not find required column(s) in the Target (Thrive) sheet: "' + missingHeaders.join('", "') + '". Please check for typos in the column names or in the CONFIG settings.');
    }

    // Get column indices
    var skuIdx = idx[CONFIG.target.sku];
    var consumerQtyIdx = idx[CONFIG.target.consumerQty];
    var wholesaleQtyIdx = idx[CONFIG.target.wholesaleQty];

    // Extract the original quantity columns as 2-D arrays for setValues()
    var consumerQtys = data.map(function(r) { return [r[consumerQtyIdx]]; });
    var wholesaleQtys = data.map(function(r) { return [r[wholesaleQtyIdx]]; });

    console.log('Original consumer quantities (first 5):', consumerQtys.slice(0, 5));
    console.log('Original wholesale quantities (first 5):', wholesaleQtys.slice(0, 5));

    var applied = {};
    var matchCount = 0;
    var updatedCount = 0;
    data.forEach(function (row, i) {
      // Normalise SKU from the target sheet for matching purposes only
      var sku = String(row[skuIdx] || '').trim();
      if (sku && sourceMap[sku]) {
        var entry = sourceMap[sku];
        
        // Ensure values are simple numbers/strings, not arrays
        var consumerValue = entry.consumerQty;
        var wholesaleValue = entry.wholesaleQty;
        
        // Convert to number if possible, otherwise use 0
        if (Array.isArray(consumerValue)) consumerValue = consumerValue[0];
        if (Array.isArray(wholesaleValue)) wholesaleValue = wholesaleValue[0];
        
        consumerValue = isNaN(Number(consumerValue)) ? 0 : Number(consumerValue);
        wholesaleValue = isNaN(Number(wholesaleValue)) ? 0 : Number(wholesaleValue);
        
        // Only update cells if the source value is greater than 0
        // Otherwise leave the existing value unchanged (keep it blank or whatever it was)
        if (consumerValue > 0) {
          consumerQtys[i] = [consumerValue];
          updatedCount++;
        }
        if (wholesaleValue > 0) {
          wholesaleQtys[i] = [wholesaleValue];
          updatedCount++;
        }
        
        applied[sku] = true;
        matchCount++;
        
        if (matchCount <= 3) { // Log first 3 matches for debugging
          console.log('Match found - SKU:', sku, 'Consumer:', consumerValue, 'Wholesale:', wholesaleValue);
          console.log('Will update consumer:', consumerValue > 0, 'Will update wholesale:', wholesaleValue > 0);
          console.log('Array structure check - Consumer:', JSON.stringify(consumerQtys[i]), 'Wholesale:', JSON.stringify(wholesaleQtys[i]));
        }
      }
    });

    console.log('Total matches found:', matchCount);
    console.log('Total cells updated:', updatedCount);
    console.log('Updated consumer quantities (first 5):', consumerQtys.slice(0, 5));
    console.log('Updated wholesale quantities (first 5):', wholesaleQtys.slice(0, 5));

    // Write back only the two (unprotected) quantity columns
    var startRow = CONFIG.target.headerRow + 1;
    console.log('Writing to start row:', startRow);
    console.log('Consumer column (1-based):', consumerQtyIdx + 1);
    console.log('Wholesale column (1-based):', wholesaleQtyIdx + 1);
    console.log('Writing', consumerQtys.length, 'rows');
    
    // Check if we have edit permissions
    try {
      var testRange = targetSheet.getRange(startRow, consumerQtyIdx + 1, 1, 1);
      console.log('Current value in test cell:', testRange.getValue());
      
      // Validate data structure before writing
      console.log('Data validation - consumerQtys sample:', JSON.stringify(consumerQtys.slice(0, 3)));
      console.log('Data validation - wholesaleQtys sample:', JSON.stringify(wholesaleQtys.slice(0, 3)));
      
      // Double-check that all entries are single-element arrays
      var badConsumerEntries = consumerQtys.filter(function(entry, idx) {
        return !Array.isArray(entry) || entry.length !== 1;
      });
      var badWholesaleEntries = wholesaleQtys.filter(function(entry, idx) {
        return !Array.isArray(entry) || entry.length !== 1;
      });
      
      if (badConsumerEntries.length > 0) {
        console.error('Invalid consumer entries found:', badConsumerEntries.slice(0, 3));
        throw new Error('Consumer data validation failed. Found entries that are not single-element arrays.');
      }
      
      if (badWholesaleEntries.length > 0) {
        console.error('Invalid wholesale entries found:', badWholesaleEntries.slice(0, 3));
        throw new Error('Wholesale data validation failed. Found entries that are not single-element arrays.');
      }
      
      // Perform the actual writes
      targetSheet.getRange(startRow, consumerQtyIdx + 1, consumerQtys.length, 1).setValues(consumerQtys);
      console.log('Successfully wrote consumer quantities');
      
      targetSheet.getRange(startRow, wholesaleQtyIdx + 1, wholesaleQtys.length, 1).setValues(wholesaleQtys);
      console.log('Successfully wrote wholesale quantities');
      
      // Verify the write worked
      var verifyRange = targetSheet.getRange(startRow, consumerQtyIdx + 1, Math.min(3, consumerQtys.length), 1);
      var verifyValues = verifyRange.getValues();
      console.log('Verification - first 3 consumer values after write:', verifyValues);
      
    } catch (writeError) {
      console.error('Write error:', writeError.toString());
      throw new Error('Failed to write to target sheet. Error: ' + writeError.toString() + '. You may not have edit permissions for this sheet.');
    }

    console.log('Data writes completed successfully, preparing log...');

    // Prepare log
    try {
      var missing = Object.keys(sourceMap).filter(function (s) { return !applied[s]; });
      console.log('Missing SKUs count:', missing.length);
      
      var logRows = [];
      if (missing.length) {
        logRows.push(['SKU', 'Error']);
        missing.forEach(function (s) { logRows.push([s, 'Not found in Thrive sheet']); });
      } else {
        logRows.push(['✅ All source SKUs updated successfully']);
      }
      
      logRows.push(['Debug Info', 'Value']);
      logRows.push(['Total Source SKUs', Object.keys(sourceMap).length]);
      logRows.push(['Total Target Rows', data.length]);
      logRows.push(['Successful Matches', matchCount]);
      logRows.push(['Cells Updated (non-zero)', updatedCount]);
      logRows.push(['Execution Time', new Date().toString()]);
      
      console.log('Log data prepared, writing to log sheet...');
      console.log('Log rows structure (first 3):', JSON.stringify(logRows.slice(0, 3)));
      
      writeLog(logRows);
      console.log('Log written successfully');
      
    } catch (logError) {
      console.error('Error writing log:', logError.toString());
      throw new Error('Data update completed but log writing failed: ' + logError.toString());
    }

    console.log('Copy job completed successfully');
    ui.alert('Copy complete.\nMatched ' + matchCount + ' SKUs.\nSee "' + CONFIG.logSheetName + '" for details.');
  } catch (e) {
    console.error('Error in runCopyJob:', e.toString());
    ui.alert('Error: ' + e);
  }
}

/* ============================ MENU ENTRIES ============================== */

function copyInventory() {
  var sourceUrl = promptForSourceUrl();
  var targetUrl = promptForTargetUrl();
  runCopyJob(sourceUrl, targetUrl);
}

/**
 * Debug function to help troubleshoot script issues
 * Run this from the Apps Script editor to check basic functionality
 */
function debugScript() {
  console.log('=== DEBUG SCRIPT START ===');
  
  try {
    // Test 1: Check if we can access the current spreadsheet
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    console.log('Current spreadsheet name:', ss.getName());
    console.log('Current spreadsheet URL:', ss.getUrl());
    
    // Test 2: Check available sheets
    var sheets = ss.getSheets();
    console.log('Available sheets:', sheets.map(function(s) { return s.getName(); }));
    
    // Test 3: Check if we can create/access the log sheet
    var logSheet = ss.getSheetByName(CONFIG.logSheetName);
    if (!logSheet) {
      console.log('Log sheet does not exist, creating...');
      logSheet = ss.insertSheet(CONFIG.logSheetName);
    }
    console.log('Log sheet name:', logSheet.getName());
    
    // Test 4: Try to write to log sheet
    logSheet.getRange('A1').setValue('Debug test at ' + new Date().toString());
    console.log('Successfully wrote to log sheet');
    
    // Test 5: Check configuration
    console.log('CONFIG.source:', CONFIG.source);
    console.log('CONFIG.target:', CONFIG.target);
    
    console.log('=== DEBUG SCRIPT COMPLETED SUCCESSFULLY ===');
    
  } catch (error) {
    console.error('Debug script error:', error.toString());
    console.log('=== DEBUG SCRIPT FAILED ===');
  }
}

/**
 * Function to inspect target sheet headers
 * Prompts for target URL and shows exactly what headers are found
 */
function inspectTargetHeaders() {
  try {
    var targetUrl = promptForTargetUrl();
    var targetSheet = openFirstSheet(targetUrl);
    
    console.log('=== TARGET SHEET HEADER INSPECTION ===');
    console.log('Sheet name:', targetSheet.getName());
    console.log('Reading headers from row:', CONFIG.target.headerRow);
    
    var sheetInfo = getSheetData(targetSheet, CONFIG.target.headerRow);
    var headers = sheetInfo.headers;
    
    console.log('Total columns found:', headers.length);
    console.log('All headers:', JSON.stringify(headers));
    
    // Show headers with their column numbers
    headers.forEach(function(header, index) {
      if (header) {
        console.log('Column ' + (index + 1) + ': "' + header + '"');
      }
    });
    
    // Check for expected headers
    console.log('\n=== LOOKING FOR EXPECTED HEADERS ===');
    var expectedHeaders = [CONFIG.target.sku, CONFIG.target.consumerQty, CONFIG.target.wholesaleQty];
    expectedHeaders.forEach(function(expected) {
      var found = headers.indexOf(expected);
      if (found >= 0) {
        console.log('✅ Found "' + expected + '" at column ' + (found + 1));
      } else {
        console.log('❌ Missing "' + expected + '"');
        // Look for similar
        var similar = headers.filter(function(h) {
          return h && h.toString().toLowerCase().indexOf(expected.toLowerCase().substring(0, 5)) >= 0;
        });
        if (similar.length > 0) {
          console.log('   Similar headers: ' + JSON.stringify(similar));
        }
      }
    });
    
    SpreadsheetApp.getUi().alert('Header inspection complete. Check the console logs for details.');
    
  } catch (error) {
    console.error('Header inspection error:', error.toString());
    SpreadsheetApp.getUi().alert('Error inspecting headers: ' + error.toString());
  }
}

/**
 * Function to inspect source sheet headers
 * Prompts for source URL and shows exactly what headers are found
 */
function inspectSourceHeaders() {
  try {
    var sourceUrl = promptForSourceUrl();
    var sourceSheet = openFirstSheet(sourceUrl);
    
    console.log('=== SOURCE SHEET HEADER INSPECTION ===');
    console.log('Sheet name:', sourceSheet.getName());
    console.log('Reading headers from row:', CONFIG.source.headerRow);
    
    var sheetInfo = getSheetData(sourceSheet, CONFIG.source.headerRow);
    var headers = sheetInfo.headers;
    var data = sheetInfo.data;
    
    console.log('Total columns found:', headers.length);
    console.log('Total data rows found:', data.length);
    console.log('All headers:', JSON.stringify(headers));
    
    // Show headers with their column numbers
    headers.forEach(function(header, index) {
      if (header) {
        console.log('Column ' + (index + 1) + ': "' + header + '"');
      }
    });
    
    // Check for expected headers
    console.log('\n=== LOOKING FOR EXPECTED SOURCE HEADERS ===');
    var expectedHeaders = [CONFIG.source.sku, CONFIG.source.consumerQty, CONFIG.source.wholesaleQty];
    expectedHeaders.forEach(function(expected) {
      var found = headers.indexOf(expected);
      if (found >= 0) {
        console.log('✅ Found "' + expected + '" at column ' + (found + 1));
      } else {
        console.log('❌ Missing "' + expected + '"');
        // Look for similar
        var similar = headers.filter(function(h) {
          return h && h.toString().toLowerCase().indexOf(expected.toLowerCase().substring(0, 3)) >= 0;
        });
        if (similar.length > 0) {
          console.log('   Similar headers: ' + JSON.stringify(similar));
        }
      }
    });
    
    // Show sample data
    if (data.length > 0) {
      console.log('\n=== SAMPLE DATA (first 3 rows) ===');
      for (var i = 0; i < Math.min(3, data.length); i++) {
        console.log('Row ' + (i + 1) + ':', JSON.stringify(data[i].slice(0, Math.min(6, data[i].length))));
      }
    }
    
    SpreadsheetApp.getUi().alert('Source header inspection complete. Check the console logs for details.');
    
  } catch (error) {
    console.error('Source header inspection error:', error.toString());
    SpreadsheetApp.getUi().alert('Error inspecting source headers: ' + error.toString());
  }
}

/**
 * Function to analyze match statistics between source and target
 * Helps verify if high match counts are accurate or indicate data limits
 */
function analyzeMatchStatistics() {
  try {
    var sourceUrl = promptForSourceUrl();
    var targetUrl = promptForTargetUrl();
    
    console.log('=== MATCH ANALYSIS ===');
    
    // Build source map
    var sourceSheet = openFirstSheet(sourceUrl);
    var sourceMap = buildSourceMap(sourceSheet);
    var sourceSKUs = Object.keys(sourceMap);
    
    // Get target data
    var targetSheet = openFirstSheet(targetUrl);
    var sheetInfo = getSheetData(targetSheet, CONFIG.target.headerRow);
    var data = sheetInfo.data;
    var headers = sheetInfo.headers;
    var idx = buildHeaderIndex(headers);
    var skuIdx = idx[CONFIG.target.sku];
    
    // Count matches and analyze
    var targetSKUs = [];
    var matches = [];
    var nonZeroMatches = [];
    
    data.forEach(function(row) {
      var sku = String(row[skuIdx] || '').trim();
      if (sku) {
        targetSKUs.push(sku);
        if (sourceMap[sku]) {
          matches.push(sku);
          var entry = sourceMap[sku];
          if (entry.consumerQty > 0 || entry.wholesaleQty > 0) {
            nonZeroMatches.push(sku);
          }
        }
      }
    });
    
    // Find unmatched SKUs
    var unmatchedSource = sourceSKUs.filter(function(sku) {
      return targetSKUs.indexOf(sku) === -1;
    });
    
    var unmatchedTarget = targetSKUs.filter(function(sku) {
      return sourceSKUs.indexOf(sku) === -1;
    });
    
    console.log('=== ANALYSIS RESULTS ===');
    console.log('Source SKUs:', sourceSKUs.length);
    console.log('Target SKUs:', targetSKUs.length);
    console.log('Matches found:', matches.length);
    console.log('Matches with non-zero quantities:', nonZeroMatches.length);
    console.log('Unmatched in source (not in target):', unmatchedSource.length);
    console.log('Unmatched in target (not in source):', unmatchedTarget.length);
    
    // Show match percentage
    var matchPercentage = (matches.length / sourceSKUs.length * 100).toFixed(1);
    console.log('Match rate:', matchPercentage + '%');
    
    // Show sample unmatched if any
    if (unmatchedSource.length > 0) {
      console.log('Sample unmatched source SKUs:', unmatchedSource.slice(0, 5));
    }
    if (unmatchedTarget.length > 0) {
      console.log('Sample unmatched target SKUs:', unmatchedTarget.slice(0, 5));
    }
    
    // Check for potential data limits
    if (sourceSKUs.length === 500) {
      console.log('⚠️  WARNING: Source has exactly 500 SKUs - this might indicate a data limit in your source sheet');
    }
    if (matches.length === sourceSKUs.length) {
      console.log('✅ All source SKUs were found in target (100% match rate)');
    }
    
    var message = 'Analysis complete:\n' +
                  'Source: ' + sourceSKUs.length + ' SKUs\n' +
                  'Target: ' + targetSKUs.length + ' SKUs\n' +
                  'Matches: ' + matches.length + ' (' + matchPercentage + '%)\n' +
                  'Non-zero matches: ' + nonZeroMatches.length + '\n' +
                  'Check console for details.';
    
    SpreadsheetApp.getUi().alert('Match Analysis', message, SpreadsheetApp.getUi().ButtonSet.OK);
    
  } catch (error) {
    console.error('Analysis error:', error.toString());
    SpreadsheetApp.getUi().alert('Error during analysis: ' + error.toString());
  }
}

/* ========================= ADD-ON HOMEPAGE ============================ */

/**
 * Creates a homepage card for the add-on.
 * This is triggered when a user opens the add-on from the Extensions menu.
 * @param {Object} e The event object.
 * @return {CardService.Card} The card to display.
 */
function onHomepage(e) {
  var copyButton = CardService.newTextButton()
    .setText('Copy Inventory')
    .setOnClickAction(CardService.newAction().setFunctionName('copyInventory'));

  var card = CardService.newCardBuilder()
    .setHeader(CardService.newCardHeader().setTitle('Inventory Sync'))
    .addSection(
      CardService.newCardSection()
        .addWidget(CardService.newTextParagraph().setText('Run a sync task:'))
        .addWidget(copyButton)
    )
    .build();

  return card;
} 