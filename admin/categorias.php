<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: categorias.php"); exit;
}
$stmt = $pdo->query("SELECT * FROM categorias ORDER BY pos ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
include 'header.php';
?>
<div class="panel-card">
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; align-items:center; flex-wrap:wrap; gap:12px;">
        <h2 style="margin:0;font-family:Oswald;text-transform:uppercase;color:#1d3557;">Categorías</h2>
        <div style="display:flex; gap:12px; align-items:center;">
            <input type="text" id="searchInput" placeholder="Buscar categoría o subtítulo..." style="padding:8px 12px; background:#f7f7f7; border:1px solid #e5e5e5; border-radius:6px; outline:none; font-size:13px; width:240px; font-family:'Inter', sans-serif;">
            <a href="categoria_form.php" class="btn-primary" style="white-space:nowrap;">+ Nueva Categoría</a>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="products-table" id="categoriesTable">
            <thead>
                <tr>
                    <th style="width:70px;">Orden</th>
                    <th style="width:90px;">Imagen</th>
                    <th>Nombre</th>
                    <th>Subtítulo (Tag)</th>
                    <th style="width:110px;">En Inicio</th>
                    <th style="width:140px; text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $m): ?>
                    <tr class="category-row">
                        <td class="searchable" style="color:#64748b; font-weight:600;"><?= $m['pos'] ?></td>
                        <td><?php if($m['img']): ?><img src="../<?= htmlspecialchars($m['img']) ?>" style="height:32px; max-width:60px; object-fit:contain;" /><?php else: ?><span style="font-size:11px; color:#cbd5e1;">Sin imagen</span><?php endif; ?></td>
                        <td class="searchable"><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                        <td class="searchable" style="color:#64748b; font-size:13px;"><?= htmlspecialchars($m['tag'] ?? '') ?></td>
                        <td>
                            <?php if(!isset($m['mostrar_home']) || $m['mostrar_home'] == 1): ?>
                                <span style="background:#e6fffa; color:#234e52; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700; border:1px solid #b2f5ea;">Visible</span>
                            <?php else: ?>
                                <span style="background:#edf2f7; color:#718096; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600;">Oculto</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                            <a href="categoria_form.php?id=<?= $m['id'] ?>" class="action-link">Editar</a>
                            <a href="categorias.php?delete=<?= $m['id'] ?>" class="action-link danger" onclick="return confirm('¿Seguro que deseas eliminar esta categoría?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr id="noResultsRow" style="display:none;"><td colspan="6" style="text-align:center; padding:35px; color:#718096; font-size:14px;">No se encontraron categorías.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación de Categorías -->
    <div id="paginationContainer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 24px; padding-top: 18px; border-top: 1px solid #eee; font-family: 'Inter', sans-serif;">
        <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: #64748b;">
            <span id="paginationInfo">Mostrando 0 - 0 de 0 categorías</span>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span>Mostrar:</span>
                <select id="itemsPerPageSelect" style="padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff; color: #1e293b; outline: none; cursor: pointer;">
                    <option value="10" selected>10 por pág.</option>
                    <option value="20">20 por pág.</option>
                    <option value="50">50 por pág.</option>
                    <option value="all">Ver todas</option>
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
    const rows = document.querySelectorAll('.category-row');
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
            paginationInfo.textContent = 'Mostrando 0 de 0 categorías';
        } else {
            paginationInfo.textContent = `Mostrando ${startIndex + 1} - ${endIndex} de ${totalItems} categorías`;
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
</body></html>
