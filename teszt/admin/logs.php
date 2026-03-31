<?php
require 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Rendszernaplók - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .log-table {
            width: 100%;
            font-family: monospace;
            font-size: 0.9rem;
            border-collapse: collapse;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .log-table th,
        .log-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .log-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Spinner Styles */
        #spinner {
            text-align: center;
            padding: 2rem;
        }

        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Error Message */
        #error-message {
            display: none;
            background-color: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #ffcdd2;
            text-align: center;
        }
    </style>
</head>

<body>

    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between;">
        <div style="font-weight: bold;">Szívhangja Admin</div>
        <a href="index.php" style="color: white; text-decoration: none;">&larr; Vissza</a>
    </header>

    <div class="container" style="max-width: 1400px; margin-top: 2rem;">
        <h1>Audit Napló (Admin Logs)</h1>
        <p style="color: #666; margin-bottom: 2rem;">Az utolsó 100 adminisztrátori művelet.</p>

        <div id="error-message">Audit napló nem elérhető</div>

        <div id="spinner">
            <div class="loader"></div>
            <p>Naplók betöltése...</p>
        </div>

        <table class="log-table" id="logs-table" style="display: none;">
            <thead>
                <tr>
                    <th>Dátum</th>
                    <th>Admin</th>
                    <th>Művelet</th>
                    <th>Célpont (ID)</th>
                    <th>IP Cím</th>
                    <th>Részletek</th>
                </tr>
            </thead>
            <tbody id="logs-body">
                <!-- Javascript tölti be -->
            </tbody>
        </table>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('logs-table');
            const tableBody = document.getElementById('logs-body');
            const spinner = document.getElementById('spinner');
            const errorMsg = document.getElementById('error-message');

            fetch('api_logs.php')
                .then(async response => {
                    const isJson = response.headers.get('content-type')?.includes('application/json');
                    const data = isJson ? await response.json() : null;

                    if (!response.ok) {
                        const error = (data && data.error) || 'HTTP hiba: ' + response.status;
                        throw new Error(error);
                    }
                    return data;
                })
                .then(result => {
                    if (!result || !result.success) {
                        throw new Error(result?.error || 'Ismeretlen hiba történt a szerver válaszában');
                    }

                    const logs = result.data;

                    if (!logs || logs.length === 0) {
                        table.style.display = 'table';
                        tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: #666;">Nincs naplóbejegyzés.</td></tr>';
                        return;
                    }

                    let html = '';
                    logs.forEach(log => {
                        const targetText = log.target_id ? '#' + log.target_id : '-';

                        html += `
                            <tr>
                                <td style="white-space: nowrap; color: #555;">${escapeHtml(log.created_at)}</td>
                                <td><strong>${escapeHtml(log.admin_name)}</strong></td>
                                <td style="color: #0277bd; font-weight: bold;">${escapeHtml(log.action)}</td>
                                <td>${escapeHtml(targetText)}</td>
                                <td>${escapeHtml(log.ip_address)}</td>
                                <td style="color: #444;">${escapeHtml(log.details)}</td>
                            </tr>
                        `;
                    });

                    tableBody.innerHTML = html;
                    table.style.display = 'table';
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    errorMsg.style.display = 'block';
                    errorMsg.textContent = 'Hiba történt: ' + err.message;
                    // Megjelenítjük az üres táblázatot hiba felett ha szükséges, vagy csak a hibaüzenetet?
                    // A spinner el fog tűnni a finally-ban, így a hibaüzenet látható lesz.
                })
                .finally(() => {
                    spinner.style.display = 'none';
                });
        });

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return text.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>