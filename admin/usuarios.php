<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Control de acceso al módulo web
if (isset($_SESSION['acceso_web']) && $_SESSION['acceso_web'] == 0) {
    if (isset($_SESSION['acceso_cotizador']) && $_SESSION['acceso_cotizador'] == 1) {
        header("Location: ../v1/cotizaciones/cotizador.html");
    } else {
        header("Location: logout.php");
    }
    exit;
}

// Solo administradores pueden gestionar usuarios
if (($_SESSION['rol'] ?? '') !== 'admin') {
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'><h2>Acceso denegado</h2><p>Solo usuarios con rol Administrador pueden gestionar los perfiles de usuario.</p><p><a href='index.php'>Regresar al inicio</a></p></div>");
}

// Eliminar usuario
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId === (int)$_SESSION['user_id']) {
        $_SESSION['flash_error'] = "No puedes eliminar tu propia cuenta en sesión.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$deleteId]);
        $_SESSION['flash_success'] = "Usuario eliminado correctamente.";
    }
    header("Location: usuarios.php");
    exit;
}

$stmt = $pdo->query("SELECT id, username, nombre, rol, acceso_web, acceso_cotizador, created_at FROM usuarios ORDER BY nombre ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include 'header.php';
?>

<div class="panel-card">
    <div style="display:flex; justify-content:space-between; margin-bottom:24px; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0;font-family:'Oswald',sans-serif;text-transform:uppercase;color:#1d3557;font-size:24px;">
                👥 Perfiles de Usuario y Permisos
            </h2>
            <p style="margin:4px 0 0; color:#718096; font-size:13px;">
                Define qué usuarios tienen acceso al Administrador Web, al Módulo del Cotizador o a ambos.
            </p>
        </div>
        <div style="display:flex; gap:12px; align-items:center;">
            <input type="text" id="searchInput" placeholder="Buscar usuario o nombre..." style="padding:8px 12px; background:#f7f7f7; border:1px solid #e5e5e5; border-radius:6px; outline:none; font-size:13px; width:220px; font-family:'Inter', sans-serif;">
            <a href="usuario_form.php" class="btn-primary" style="background:#e63946; display:flex; align-items:center; gap:6px; white-space:nowrap;">
                <span>+</span> Nuevo Usuario
            </a>
        </div>
    </div>

    <?php if ($flash_success): ?>
        <div style="background:#e6fffa;color:#234e52;padding:12px 16px;border-radius:8px;margin-bottom:20px;border:1px solid #b2f5ea;font-size:14px;">
            ✅ <?= htmlspecialchars($flash_success) ?>
        </div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div style="background:#fff5f5;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:20px;border:1px solid #feb2b2;font-size:14px;">
            ⚠️ <?= htmlspecialchars($flash_error) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="products-table" id="usersTable">
            <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Usuario</th>
                    <th style="text-align:center;">Rol Web</th>
                    <th style="text-align:center;">Rol Cotizador</th>
                    <th>Fecha Alta</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr class="user-row">
                        <td class="searchable">
                            <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span style="font-size:10px; background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:10px; margin-left:6px; font-weight:700;">Tú</span>
                            <?php endif; ?>
                        </td>
                        <td class="searchable" style="color:#4a5568;"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="searchable" style="text-align:center;">
                            <?php 
                            $rWeb = $u['rol_web'] ?? (!empty($u['acceso_web']) ? ($u['rol'] === 'admin' ? 'admin' : 'admin') : 'none');
                            if ($rWeb === 'admin'): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#0369a1; font-weight:700; font-size:11px; background:#e0f2fe; padding:4px 10px; border-radius:12px; border:1px solid #bae6fd; text-transform:uppercase;">
                                    🛡️ Admin Web
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#dc2626; font-weight:600; font-size:11px; background:#fef2f2; padding:3px 8px; border-radius:6px; border:1px solid #fecaca;">
                                    🚫 Sin Acceso
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="searchable" style="text-align:center;">
                            <?php 
                            $rCotiz = $u['rol_cotizador'] ?? (!empty($u['acceso_cotizador']) ? ($u['rol'] === 'admin' ? 'admin' : 'comercial') : 'none');
                            if ($rCotiz === 'admin'): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#b91c1c; font-weight:700; font-size:11px; background:#fee2e2; padding:4px 10px; border-radius:12px; border:1px solid #fecaca; text-transform:uppercase;">
                                    👑 Admin Cotizador
                                </span>
                            <?php elseif ($rCotiz === 'comercial'): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#16a34a; font-weight:600; font-size:11px; background:#f0fdf4; padding:3px 8px; border-radius:6px; border:1px solid #bbf7d0;">
                                    👤 Asesor Comercial
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#dc2626; font-weight:600; font-size:11px; background:#fef2f2; padding:3px 8px; border-radius:6px; border:1px solid #fecaca;">
                                    🚫 Sin Acceso
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#718096; font-size:12px;">
                            <?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '-' ?>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                            <a href="usuario_form.php?id=<?= $u['id'] ?>" class="action-link" style="color:#1d3557;">Editar</a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="usuarios.php?delete=<?= $u['id'] ?>" class="action-link danger" onclick="return confirm('¿Estás seguro de eliminar el usuario <?= addslashes($u['username']) ?>?');">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr id="noResultsRow" style="display:none;"><td colspan="6" style="text-align:center; padding:35px; color:#718096; font-size:14px;">No se encontraron usuarios.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación de Usuarios -->
    <div id="paginationContainer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 24px; padding-top: 18px; border-top: 1px solid #eee; font-family: 'Inter', sans-serif;">
        <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: #64748b;">
            <span id="paginationInfo">Mostrando 0 - 0 de 0 usuarios</span>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span>Mostrar:</span>
                <select id="itemsPerPageSelect" style="padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff; color: #1e293b; outline: none; cursor: pointer;">
                    <option value="10" selected>10 por pág.</option>
                    <option value="20">20 por pág.</option>
                    <option value="50">50 por pág.</option>
                    <option value="all">Ver todos</option>
                </select>
            </div>
        </div>

        <div id="paginationNav" style="display: flex; align-items: center; gap: 6px;">
            <!-- Botones dinámicos generados por JS -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.user-row');
    const noResultsRow = document.getElementById('noResultsRow');

    const paginationContainer = document.getElementById('paginationContainer');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationNav = document.getElementById('paginationNav');
    const itemsPerPageSelect = document.getElementById('itemsPerPageSelect');

    let currentPage = 1;
    let itemsPerPage = 10;
    let matchingRows = Array.from(rows);

    if (itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', function() {
            const val = this.value;
            itemsPerPage = (val === 'all') ? Infinity : parseInt(val, 10);
            currentPage = 1;
            renderPage();
        });
    }

    function renderPage() {
        const totalItems = matchingRows.length;
        const totalPages = itemsPerPage === Infinity ? 1 : Math.max(1, Math.ceil(totalItems / itemsPerPage));

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = (itemsPerPage === Infinity) ? totalItems : Math.min(startIndex + itemsPerPage, totalItems);

        // Ocultar todas las filas
        rows.forEach(r => r.style.display = 'none');
        // Mostrar solo las del rango en la página actual
        matchingRows.slice(startIndex, endIndex).forEach(r => r.style.display = '');

        // Fila de sin resultados
        if (noResultsRow) {
            noResultsRow.style.display = (matchingRows.length === 0) ? '' : 'none';
        }

        // Información de paginación
        if (totalItems === 0) {
            paginationInfo.textContent = 'Mostrando 0 de 0 usuarios';
        } else {
            paginationInfo.textContent = `Mostrando ${startIndex + 1} - ${endIndex} de ${totalItems} usuarios`;
        }

        renderPaginationButtons(totalPages);
    }

    function renderPaginationButtons(totalPages) {
        if (!paginationNav) return;
        paginationNav.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        const createBtn = (html, page, isDisabled = false, isActive = false) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = html;
            btn.disabled = isDisabled;
            btn.style.cssText = `
                min-width: 34px;
                height: 34px;
                padding: 0 8px;
                border-radius: 6px;
                border: 1px solid ${isActive ? '#457b9d' : '#cbd5e1'};
                background: ${isActive ? '#457b9d' : (isDisabled ? '#f8fafc' : '#ffffff')};
                color: ${isActive ? '#ffffff' : (isDisabled ? '#94a3b8' : '#1e293b')};
                font-weight: ${isActive ? '700' : '600'};
                font-size: 13px;
                cursor: ${isDisabled ? 'not-allowed' : 'pointer'};
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: all 0.15s ease;
            `;
            if (!isDisabled && !isActive) {
                btn.addEventListener('mouseenter', () => btn.style.background = '#f1f5f9');
                btn.addEventListener('mouseleave', () => btn.style.background = '#ffffff');
                btn.addEventListener('click', () => {
                    currentPage = page;
                    renderPage();
                    window.scrollTo({ top: document.querySelector('.panel-card').offsetTop - 20, behavior: 'smooth' });
                });
            }
            return btn;
        };

        // Botón Anterior
        paginationNav.appendChild(createBtn('&laquo;', currentPage - 1, currentPage === 1));

        // Rango de páginas con elipses
        const delta = 2;
        const range = [];
        for (let i = Math.max(2, currentPage - delta); i <= Math.min(totalPages - 1, currentPage + delta); i++) {
            range.push(i);
        }

        // Primera página
        paginationNav.appendChild(createBtn('1', 1, false, currentPage === 1));

        if (range.length > 0 && range[0] > 2) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.cssText = 'padding: 0 4px; color: #94a3b8; font-weight: 600;';
            paginationNav.appendChild(dots);
        }

        range.forEach(p => {
            paginationNav.appendChild(createBtn(p.toString(), p, false, currentPage === p));
        });

        if (range.length > 0 && range[range.length - 1] < totalPages - 1) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.cssText = 'padding: 0 4px; color: #94a3b8; font-weight: 600;';
            paginationNav.appendChild(dots);
        }

        // Última página
        if (totalPages > 1) {
            paginationNav.appendChild(createBtn(totalPages.toString(), totalPages, false, currentPage === totalPages));
        }

        // Botón Siguiente
        paginationNav.appendChild(createBtn('&raquo;', currentPage + 1, currentPage === totalPages));
    }

    function filterTable() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();

        matchingRows = Array.from(rows).filter(row => {
            if (query !== '') {
                const searchData = Array.from(row.querySelectorAll('.searchable'))
                                      .map(td => td.textContent.toLowerCase())
                                      .join(' ');
                if (!searchData.includes(query)) return false;
            }
            return true;
        });

        currentPage = 1;
        renderPage();
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    // Inicializar
    filterTable();
});
</script>
</body>
</html>
