/**
 * ICCAT CATFÉ — Google Sheets order sync
 *
 * SETUP:
 * 1. Create a new Google Sheet. Name the first tab "Orders".
 *    In row 1, add these headers (exact spelling, columns A–J):
 *    order_id | email | first_name | last_name | city | region | phone_number | address | orders | total_payment | status
 *    (that's 11 columns, A through K)
 *
 * 2. In the Sheet, go to Extensions > Apps Script, delete any starter code,
 *    and paste this whole file in.
 *
 * 3. Click Deploy > New deployment > select type "Web app".
 *    - Execute as: Me
 *    - Who has access: Anyone
 *    Click Deploy and copy the Web App URL.
 *
 * 4. Paste that URL into includes/functions.php as GOOGLE_SHEET_WEBHOOK_URL.
 *
 * HOW IT WORKS:
 * - When a customer checks out, PHP POSTs the order here and it's appended as a new row.
 * - Café staff can edit the "status" column (K) directly in the Sheet:
 *   Pending / Processing / Out for Delivery / Delivered / Cancelled
 * - The website calls this script (GET ?action=get_statuses) whenever a user
 *   opens "View Orders", pulling in whatever status is currently in the Sheet.
 */

const SHEET_NAME = "Orders";

function doPost(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
  const data = JSON.parse(e.postData.contents);

  if (data.action === "add_order") {
    sheet.appendRow([
      data.order_id,
      data.email,
      data.first_name,
      data.last_name,
      data.city,
      data.region,
      data.phone_number,
      data.address,
      data.orders,
      data.total_payment,
      data.status
    ]);
    return ContentService.createTextOutput(JSON.stringify({ status: "ok" }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  if (data.action === "update_status") {
    updateStatusInSheet(sheet, data.order_id, data.status);
    return ContentService.createTextOutput(JSON.stringify({ status: "ok" }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  return ContentService.createTextOutput(JSON.stringify({ status: "error", message: "Unknown action" }))
    .setMimeType(ContentService.MimeType.JSON);
}

function doGet(e) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
  const action = e.parameter.action;

  if (action === "get_statuses") {
    const values = sheet.getDataRange().getValues();
    const rows = [];
    // skip header row (index 0)
    for (let i = 1; i < values.length; i++) {
      rows.push({
        order_id: values[i][0],
        status: values[i][10]
      });
    }
    return ContentService.createTextOutput(JSON.stringify(rows))
      .setMimeType(ContentService.MimeType.JSON);
  }

  return ContentService.createTextOutput(JSON.stringify({ status: "error", message: "Unknown action" }))
    .setMimeType(ContentService.MimeType.JSON);
}

function updateStatusInSheet(sheet, orderId, status) {
  const values = sheet.getDataRange().getValues();
  for (let i = 1; i < values.length; i++) {
    if (String(values[i][0]) === String(orderId)) {
      sheet.getRange(i + 1, 11).setValue(status); // column K = status
      break;
    }
  }
}
