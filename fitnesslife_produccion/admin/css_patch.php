<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap');
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: #333333; background: #ededed; overflow-x: hidden; }
input, textarea, select, button { font: inherit; outline: none; }
.app-header { background: white; border-bottom: 1px solid #e5e5e5; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
.app-header h1 { margin: 0; font-family: Oswald, sans-serif; text-transform: uppercase; font-size: 20px; color: #1d3557; display: flex; align-items: center; gap: 12px; }
.header-actions { display: flex; gap: 12px; }
.btn-nav { text-decoration: none; padding: 8px 16px; font-size: 13px; font-weight: 600; color: #457b9d; background: #f0f4f8; border-radius: 6px; border: 1px solid #d0e0ec; transition: 0.2s; }
.btn-nav.active { background: #e63946; color: white; border-color: #e63946; }
.btn-nav:hover:not(.active) { background: #e0ebf3; }
.btn-primary { text-decoration: none; padding: 8px 16px; font-size: 13px; font-weight: 600; color: white; background: #1d3557; border-radius: 6px; border: none; cursor: pointer; }
.btn-primary:hover { background: #142845; }
.main-container { padding: 32px; max-width: 1200px; margin: 0 auto; }
.panel-card { background: white; border-radius: 12px; border: 1px solid #e5e5e5; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
.table-wrapper { overflow-x: auto; margin: 0 -24px; padding: 0 24px; }
.products-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.products-table th { text-align: left; padding: 12px 10px; border-bottom: 2px solid #e63946; color: #1d3557; font-weight: 600; white-space: nowrap; }
.products-table td { padding: 12px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.products-table tr:hover { background: #fdfdfd; }
.action-link { color: #3182ce; text-decoration: none; font-weight: 600; margin-right: 12px; }
.action-link.danger { color: #e53e3e; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #727272; margin-bottom: 6px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; box-sizing: border-box; background: #f7f7f7; border: 1px solid #e5e5e5; border-radius: 6px; padding: 10px 12px; font-size: 14px; transition: border-color 0.2s; outline:none; }
.form-group input:focus, .form-group select:focus { border-color: #457b9d; }

@media (max-width: 768px) {
  .app-header { flex-direction: column !important; align-items: flex-start !important; gap: 16px; }
  .header-actions { width: 100%; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }
  .header-actions a, .header-actions button { flex: 1; text-align: center; justify-content: center; align-items: center; flex-direction: column !important; min-width: calc(50% - 8px); box-sizing: border-box; padding: 10px 4px !important; gap: 4px !important; font-size: 11px !important; }
  .main-container { padding: 16px; }
  .table-wrapper { overflow-x: auto; margin: 0 -16px; padding: 0 16px; }
  .products-table { display: block; width: 100%; }
  .products-table thead { display: none; }
  .products-table tbody { display: block; width: 100%; }
  .products-table tr { display: grid !important; grid-template-columns: 60px 1fr !important; gap: 12px !important; border: 1px solid #e5e5e5; border-radius: 12px; margin-bottom: 16px; padding: 16px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
  .products-table td.img-cell { grid-column: 1; grid-row: 1 / span 2; padding: 0 !important; border-bottom: none !important; width: 60px; height: 48px; display: flex; align-items: center; justify-content: center; }
  .products-table td[data-label] { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; padding: 8px 0 !important; border-bottom: 1px dashed #f0f0f0 !important; }
  .products-table td[data-label="Nombre"] { grid-column: 2; grid-row: 1; display: flex; flex-direction: column !important; align-items: flex-start; border-bottom: none !important; padding: 0 !important; }
  .products-table td[data-label="Nombre"]::before { display: none; }
  .products-table td[data-label]:not([data-label="Nombre"])::before { content: attr(data-label); font-family: Oswald, sans-serif; text-transform: uppercase; font-size: 10px; color: #e63946; font-weight: 600; letter-spacing: 0.1em; }
  .products-table td.actions-cell { grid-column: 2; grid-row: 2; padding: 0 !important; border-bottom: none !important; display: flex; justify-content: flex-end; align-items: center; }
}
</style>
