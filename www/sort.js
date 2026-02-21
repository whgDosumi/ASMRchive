// Sorts a HTML table based on the column index specified in data-sort-col.
// If data-sort-col is not present, it attempts to use the header's index.
function sortTable(th) {
    var table = getTableFromHeader(th);
    var tbody = table.getElementsByTagName('tbody')[0];
    var rows = tbody.getElementsByTagName('tr');
    var rowsArray = [];

    // Convert the HTMLCollection to a standard Array so we can sort it
    for (var i = 0; i < rows.length; i++) {
        rowsArray.push(rows[i]);
    }

    // Determine the column index to sort by
    var colIndex = th.getAttribute('data-sort-col');
    if (colIndex === null) {
        // Fallback: find the index of this header among its siblings
        var headers = th.parentNode.children;
        for (var i = 0; i < headers.length; i++) {
            if (headers[i] == th) {
                colIndex = i;
                break;
            }
        }
    } else {
        colIndex = parseInt(colIndex);
    }

    // Determine sort direction (asc or desc)
    // Default to 'asc' unless it's already 'asc', then toggle to 'desc'
    var direction = 'asc';
    if (th.classList.contains('asc')) {
        direction = 'desc';
    } else if (th.classList.contains('desc')) {
        direction = 'asc';
    }

    // Reset arrows on all headers in the same row
    var allHeaders = th.parentNode.children;
    for (var i = 0; i < allHeaders.length; i++) {
        allHeaders[i].classList.remove('asc');
        allHeaders[i].classList.remove('desc');
    }

    // Apply the new sort direction class to the clicked header
    th.classList.add(direction);

    // Sort the rows using a custom comparison function
    rowsArray.sort(function(rowA, rowB) {
        // Get the cells for the column we are sorting
        var cellA = rowA.children[colIndex];
        var cellB = rowB.children[colIndex];

        // Safety check if cells don't exist
        if (!cellA || !cellB) {
            return 0;
        }

        // Get the values to compare
        var valA = getCellValue(cellA);
        var valB = getCellValue(cellB);

        // Check if values are numbers
        var numA = parseFloat(valA);
        var numB = parseFloat(valB);

        // If both are valid numbers, compare them numerically
        if (!isNaN(numA) && !isNaN(numB) && valA !== "" && valB !== "") {
            if (direction == 'asc') {
                return numA - numB;
            } else {
                return numB - numA;
            }
        }

        // Otherwise, compare them as strings (case-insensitive)
        var stringA = valA.toLowerCase();
        var stringB = valB.toLowerCase();

        if (stringA < stringB) {
            return (direction == 'asc') ? -1 : 1;
        }
        if (stringA > stringB) {
            return (direction == 'asc') ? 1 : -1;
        }
        return 0;
    });

    // Re-append the sorted rows to the table body
    for (var i = 0; i < rowsArray.length; i++) {
        tbody.appendChild(rowsArray[i]);
    }
}

// Helper function to find the parent table of a header cell
function getTableFromHeader(th) {
    var parent = th.parentNode;
    while (parent.tagName !== 'TABLE') {
        parent = parent.parentNode;
    }
    return parent;
}

// Helper function to get the value for sorting from a cell
// Prioritizes 'data-sort-value' attribute, falls back to text content
function getCellValue(cell) {
    var val = cell.getAttribute('data-sort-value');
    if (val !== null) {
        return val;
    }
    return cell.innerText.trim();
}
