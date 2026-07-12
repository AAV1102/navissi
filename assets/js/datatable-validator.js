< !--DataTables Column Validation Fix-- >
    <script>
// Add this BEFORE the initializeModuleComponents function in dashboard.php

        /**
         * Validate table columns before DataTables initialization
         * Prevents "Incorrect column count" errors
         */
        function validateTableColumns(table) {
    try {
        const headerCount = table.querySelectorAll('thead th').length;
        const firstRow = table.querySelector('tbody tr:first-child');

        if (!firstRow) {
            console.warn('No data rows found in table, skipping DataTables');
        return false;
        }

        // Skip tables with colspan (merged cells)
        if (firstRow.querySelector('td[colspan]')) {
            console.warn('Table has colspan cells, skipping DataTables');
        return false;
        }

        // Skip tables with rowspan
        if (firstRow.querySelector('td[rowspan]') || table.querySelector('th[rowspan]')) {
            console.warn('Table has rowspan cells, skipping DataTables');
        return false;
        }

        const cellCount = firstRow.querySelectorAll('td').length;

        if (headerCount === 0) {
            console.warn('Table has no headers, skipping DataTables');
        return false;
        }

        if (headerCount !== cellCount) {
            console.error(`❌ Column mismatch: ${headerCount} headers vs ${cellCount} cells`);
            console.log('Headers:', Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim()));
            console.log('First row cells:', Array.from(firstRow.querySelectorAll('td')).map(td => td.textContent.trim().substring(0, 20)));
        return false;
        }

        console.log(`✅ Table validated: ${headerCount} columns`);
        return true;
    } catch (e) {
            console.error('Error validating table:', e);
        return false;
    }
}

        /**
         * Safe DataTables initialization with column validation
         */
        function initializeDataTablesWithValidation() {
    if (!$.fn.DataTable) {
            console.warn('DataTables library not loaded');
        return;
    }

        let initialized = 0;
        let skipped = 0;

        $('.table:not(.dataTable):not(.no-datatable)').each(function () {
        const table = this;
        const $table = $(table);

        // Skip if table looks like a layout table
        if ($table.find('thead th').length === 0 && !$table.hasClass('js-datatable')) {
            skipped++;
        return;
        }

        // VALIDATE COLUMNS FIRST
        if (!validateTableColumns(table)) {
            skipped++;
        return;
        }

        try {
            $table.DataTable({
                responsive: true,
                destroy: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [
                    { targets: '_all', className: 'text-nowrap' }
                ]
            });
        initialized++;
        console.log('✅ DataTable initialized on:', table);
        } catch (e) {
            console.error('❌ DataTable init failed:', e);
        skipped++;
        }
    });

        console.log(`📊 DataTables: ${initialized} initialized, ${skipped} skipped`);
}
    </script>
