    </div>
</main>

<!-- Quick Actions FAB (Mobile Friendly) -->
<div class="quick-action-btn" id="quickActionBtn">
    <button class="btn btn-primary btn-lg rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#quickActionModal" aria-label="Ações rápidas">
        <i class="bi bi-plus-lg"></i>
    </button>
</div>

<!-- Quick Action Modal -->
<div class="modal fade" id="quickActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ação Rápida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <a href="alunos/create.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Novo Aluno
                    </a>
                    <a href="checkin/index.php" class="btn btn-outline-success btn-lg">
                        <i class="bi bi-qr-code-scan me-2"></i>Registrar Presença
                    </a>
                    <a href="financeiro/novo.php" class="btn btn-outline-info btn-lg">
                        <i class="bi bi-currency-dollar me-2"></i>Registrar Pagamento
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi me-2"></i>
            <strong class="me-auto toast-title">Título</strong>
            <small class="text-muted">agora mesmo</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Fechar"></button>
        </div>
        <div class="toast-body">
            Mensagem de notificação
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Mobile Responsive CSS -->
<link rel="stylesheet" href="<?= assets_url('css/mobile-responsive.css') ?>">

<!-- Mobile Enhancements JS -->
<script src="<?= assets_url('js/mobile-enhancements.js') ?>"></script>

<!-- Custom Scripts -->
<script>
    // Toggle Sidebar Function (Global)
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('show');
            }
            if (sidebar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    }
    
    // Close Sidebar Function (Global)
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) sidebar.classList.remove('show');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Toggle Accordion Function (Global) - Para os botões do menu
    function toggleAccordion(button) {
        if (!button) return;
        
        const accordionItem = button.closest('.accordion-item');
        if (!accordionItem) return;
        
        const accordionCollapse = accordionItem.querySelector('.accordion-collapse');
        if (!accordionCollapse) return;
        
        const isExpanded = accordionCollapse.classList.contains('show');
        
        // Fecha todos os outros accordions no mesmo container
        const allItems = accordionItem.parentElement.querySelectorAll('.accordion-item');
        allItems.forEach(item => {
            if (item !== accordionItem) {
                const otherButton = item.querySelector('.accordion-button');
                const otherCollapse = item.querySelector('.accordion-collapse');
                if (otherButton && otherCollapse) {
                    otherButton.classList.add('collapsed');
                    otherCollapse.classList.remove('show');
                }
            }
        });
        
        // Toggle o accordion atual
        if (isExpanded) {
            button.classList.add('collapsed');
            accordionCollapse.classList.remove('show');
        } else {
            button.classList.remove('collapsed');
            accordionCollapse.classList.add('show');
        }
    }
    
    // Toggle User Dropdown Function (Global)
    function toggleUserDropdown(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        const dropdown = document.getElementById('userDropdown');
        if (!dropdown) return;
        
        const toggle = dropdown.querySelector('.custom-dropdown-toggle');
        const menu = dropdown.querySelector('.custom-dropdown-menu');
        const overlay = document.getElementById('dropdownOverlay');
        
        if (!toggle || !menu) return;
        
        const isOpen = menu.classList.contains('show');
        
        // Fecha todos os dropdowns primeiro
        closeAllDropdowns();
        
        // Se não estava aberto, abre agora
        if (!isOpen) {
            toggle.classList.add('show');
            menu.classList.add('show');
            if (overlay) overlay.classList.add('show');
        }
    }
    
    // Close All Dropdowns Function (Global)
    function closeAllDropdowns() {
        const dropdowns = document.querySelectorAll('.custom-dropdown');
        const overlay = document.getElementById('dropdownOverlay');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.custom-dropdown-toggle');
            const menu = dropdown.querySelector('.custom-dropdown-menu');
            if (toggle) toggle.classList.remove('show');
            if (menu) menu.classList.remove('show');
        });
        
        if (overlay) overlay.classList.remove('show');
    }
    
    // Initialize Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }
        
        // Sidebar Overlay
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Dropdown Overlay
        const dropdownOverlay = document.getElementById('dropdownOverlay');
        if (dropdownOverlay) {
            dropdownOverlay.addEventListener('click', closeAllDropdowns);
        }

        // Close sidebar on route change (mobile)
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
        
        // Auto-hide alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                if (typeof bootstrap !== 'undefined') {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) bsAlert.close();
                }
            }, 5000);
        });
    });
    
    // Show toast notification
    function showToast(title, message, type = 'success') {
        const toastEl = document.getElementById('liveToast');
        if (!toastEl || typeof bootstrap === 'undefined') {
            console.log(title, message, type);
            return;
        }
        
        const toast = new bootstrap.Toast(toastEl);
        
        toastEl.querySelector('.toast-title').textContent = title;
        toastEl.querySelector('.toast-body').textContent = message;
        
        const icon = toastEl.querySelector('i');
        icon.className = 'bi me-2';
        
        if (type === 'success') {
            icon.classList.add('bi-check-circle', 'text-success');
        } else if (type === 'error') {
            icon.classList.add('bi-x-circle', 'text-danger');
        } else if (type === 'warning') {
            icon.classList.add('bi-exclamation-triangle', 'text-warning');
        } else {
            icon.classList.add('bi-info-circle', 'text-primary');
        }
        
        toast.show();
    }
    
    // Confirm dialog
    function confirmarExclusao(event, message = 'Tem certeza que deseja excluir?') {
        if (!confirm(message)) {
            event.preventDefault();
            return false;
        }
        return true;
    }
    
    // Format currency input
    document.querySelectorAll('.currency-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            this.value = value.replace('.', ',');
        });
    });
    
    // Phone mask
    document.querySelectorAll('.phone-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substring(0, 11);
            if (value.length > 6) {
                this.value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}`;
            } else if (value.length > 2) {
                this.value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
            } else {
                this.value = value;
            }
        });
    });
    
    // CPF mask
    document.querySelectorAll('.cpf-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substring(0, 11);
            if (value.length > 9) {
                this.value = `${value.substring(0, 3)}.${value.substring(3, 6)}.${value.substring(6, 9)}-${value.substring(9)}`;
            } else if (value.length > 6) {
                this.value = `${value.substring(0, 3)}.${value.substring(3, 6)}.${value.substring(6)}`;
            } else if (value.length > 3) {
                this.value = `${value.substring(0, 3)}.${value.substring(3)}`;
            } else {
                this.value = value;
            }
        });
    });
</script>
</body>
</html>
