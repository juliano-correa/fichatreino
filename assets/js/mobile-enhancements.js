/**
 * Titanium Gym - Mobile Enhancements JavaScript
 * Este arquivo contém funcionalidades JavaScript específicas para dispositivos móveis
 */

(function() {
    'use strict';

    // ============================================
    // DETECÇÃO DE DISPOSITIVO
    // ============================================
    const isMobile = window.innerWidth < 992;
    const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    // ============================================
    // INICIALIZAÇÃO
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        initMobileOptimizations();
        initTouchOptimizations();
        initFormEnhancements();
        initTableEnhancements();
        initNavigationEnhancements();
    });

    // ============================================
    // OTIMIZAÇÕES MOBILE GERAIS
    // ============================================
    function initMobileOptimizations() {
        // Add mobile class to body
        document.body.classList.add(isMobile ? 'mobile-device' : 'desktop-device');
        
        if (isTouch) {
            document.body.classList.add('touch-device');
        }

        // Improve scroll behavior on iOS
        if (isMobile) {
            document.body.style.webkitOverflowScrolling = 'touch';
            
            // Permitir rolagem normal no mobile
            document.body.style.overscrollBehavior = 'auto';
            // Não forçar overflow aqui, deixar o CSS controlar
        }

        console.log('Mobile Optimizations: ' + (isMobile ? 'Enabled' : 'Disabled'));
    }

    // ============================================
    // OTIMIZAÇÕES DE TOQUE
    // ============================================
    function initTouchOptimizations() {
        if (!isTouch) return;

        // Improve tap response
        document.querySelectorAll('a, button, .btn').forEach(function(element) {
            element.style.webkitTapHighlightColor = 'rgba(0,0,0,0.1)';
            element.style.tapHighlightColor = 'rgba(0,0,0,0.1)';
        });

        // Handle long press for context menus
        let longPressTimer = null;
        let longPressElement = null;

        document.querySelectorAll('.table tbody tr').forEach(function(row) {
            row.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showContextMenu(e, row);
            });

            row.addEventListener('touchstart', function(e) {
                if (e.touches.length === 1) {
                    longPressTimer = setTimeout(function() {
                        longPressElement = row;
                        showContextMenu(e.touches[0], row);
                    }, 500);
                }
            });

            row.addEventListener('touchend', function() {
                if (longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
            });

            row.addEventListener('touchmove', function() {
                if (longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
            });
        });
    }

    // ============================================
    // MENU DE CONTEXTO MOBILE
    // ============================================
    function showContextMenu(event, row) {
        // Remove existing context menu
        const existingMenu = document.querySelector('.mobile-context-menu');
        if (existingMenu) {
            existingMenu.remove();
        }

        const contextMenu = document.createElement('div');
        contextMenu.className = 'mobile-context-menu';
        contextMenu.innerHTML = '<div class="context-menu-content"></div>';
        contextMenu.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:flex-end;justify-content:center;';

        const content = contextMenu.querySelector('.context-menu-content');
        content.innerHTML = '<div style="background:white;border-radius:16px 16px 0 0;padding:1.5rem;width:100%;max-width:400px;"></div>';
        
        const links = row.querySelectorAll('td:last-child a');
        const actionsDiv = content.querySelector('div');
        
        if (links.length > 0) {
            let buttonsHTML = '';
            links.forEach(function(link) {
                const icon = link.querySelector('i') ? link.querySelector('i').className : 'bi bi-arrow-right';
                const text = link.title || link.textContent.trim();
                const href = link.href;
                const style = link.classList.contains('text-danger') ? 'color:#dc3545;' : 'color:#0d6efd;';
                
                buttonsHTML += '<a href="' + href + '" style="display:flex;align-items:center;padding:1rem;border-bottom:1px solid #eee;text-decoration:none;' + style + '" onclick="this.closest(\'.mobile-context-menu\').remove();"><i class="' + icon + '" style="margin-right:12px;font-size:1.2rem;"></i>' + text + '</a>';
            });
            
            actionsDiv.innerHTML = buttonsHTML;
            actionsDiv.innerHTML += '<button onclick="this.closest(\'.mobile-context-menu\').remove();" style="width:100%;padding:1rem;margin-top:0.5rem;border:none;background:#f8f9fa;border-radius:8px;font-size:1rem;">Cancelar</button>';
        } else {
            actionsDiv.innerHTML = '<p style="text-align:center;color:#666;padding:1rem;">Nenhuma ação disponível</p><button onclick="this.closest(\'.mobile-context-menu\').remove();" style="width:100%;padding:1rem;border:none;background:#f8f9fa;border-radius:8px;font-size:1rem;">Fechar</button>';
        }

        document.body.appendChild(contextMenu);

        contextMenu.addEventListener('click', function(e) {
            if (e.target === contextMenu) {
                contextMenu.remove();
            }
        });
    }

    // ============================================
    // MELHORIAS EM FORMULÁRIOS
    // ============================================
    function initFormEnhancements() {
        if (!isMobile) return;

        // Improve input UX
        document.querySelectorAll('.form-control').forEach(function(input) {
            // Prevent auto-zoom on iOS
            if (window.innerWidth >= 375) {
                input.style.fontSize = '16px';
            }

            // Add clear button for text inputs
            if (input.type === 'text' || input.type === 'email') {
                addClearButton(input);
            }
        });

        // Optimize select for mobile
        document.querySelectorAll('select.form-select').forEach(function(select) {
            select.style.webkitAppearance = 'none';
            select.style.appearance = 'none';
            select.style.backgroundImage = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' fill=\'%23333\' viewBox=\'0 0 16 16\'%3E%3Cpath d=\'M8 11L3 6h10l-5 5z\'/%3E%3C/svg%3E")';
            select.style.backgroundRepeat = 'no-repeat';
            select.style.backgroundPosition = 'right 1rem center';
            select.style.paddingRight = '2.5rem';
        });
    }

    // ============================================
    // BOTÃO DE LIMPAR INPUT
    // ============================================
    function addClearButton(input) {
        const container = input.parentElement;
        if (container.classList.contains('input-group')) {
            return; // Already has input group styling
        }

        if (input.hasAttribute('data-has-clear')) return;
        input.setAttribute('data-has-clear', 'true');

        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        clearBtn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#999;cursor:pointer;padding:4px;display:none;z-index:10;';
        clearBtn.setAttribute('aria-label', 'Limpar campo');

        clearBtn.addEventListener('click', function() {
            input.value = '';
            input.dispatchEvent(new Event('input'));
            clearBtn.style.display = 'none';
            input.focus();
        });

        input.addEventListener('input', function() {
            clearBtn.style.display = input.value ? 'block' : 'none';
        });

        input.addEventListener('focus', function() {
            if (input.value) {
                clearBtn.style.display = 'block';
            }
        });

        input.addEventListener('blur', function() {
            clearBtn.style.display = 'none';
        });

        wrapper.appendChild(clearBtn);
    }

    // ============================================
    // MELHORIAS EM TABELAS
    // ============================================
    function initTableEnhancements() {
        if (!isMobile) return;

        document.querySelectorAll('.table-responsive').forEach(function(container) {
            // Add visual indicator for scrollable tables
            const indicator = document.createElement('div');
            indicator.className = 'table-scroll-indicator';
            indicator.innerHTML = '<i class="bi bi-arrow-left-right"></i> Deslize para ver mais';
            indicator.style.cssText = 'text-align:center;padding:0.5rem;font-size:0.75rem;color:#6c757d;background:#f8f9fa;border-bottom:1px solid #e9ecef;display:none;';
            
            if (container.scrollWidth > container.clientWidth) {
                indicator.style.display = 'block';
            }

            container.addEventListener('scroll', function() {
                if (container.scrollLeft > 0) {
                    indicator.style.display = 'none';
                }
            });

            container.parentNode.insertBefore(indicator, container);

            // Add touch-friendly row actions
            container.querySelectorAll('tbody tr').forEach(function(row) {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on action buttons
                    if (e.target.closest('.btn') || e.target.closest('a')) return;
                    
                    // Find first link and click it
                    const link = row.querySelector('a');
                    if (link) {
                        link.click();
                    }
                });
            });
        });
    }

    // ============================================
    // MELHORIAS EM NAVEGAÇÃO
    // ============================================
    function initNavigationEnhancements() {
        // Improve sidebar behavior
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (sidebar && overlay) {
            // Swipe to close sidebar (touch devices)
            let touchStartX = 0;
            let touchEndX = 0;

            sidebar.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            sidebar.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });

            function handleSwipe() {
                const swipeThreshold = 50;
                if (touchEndX - touchStartX > swipeThreshold && sidebar.classList.contains('show')) {
                    // Swipe right - open (shouldn't happen, but just in case)
                } else if (touchStartX - touchEndX > swipeThreshold) {
                    // Swipe left - close
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    // Não manipular overflow do body, deixar o CSS controlar
                }
            }
        }

        // Improve breadcrumb on mobile
        const breadcrumbs = document.querySelectorAll('.breadcrumb');
        breadcrumbs.forEach(function(breadcrumb) {
            if (isMobile) {
                breadcrumb.classList.add('d-none');
                // Show simplified breadcrumb
                const items = breadcrumb.querySelectorAll('.breadcrumb-item');
                if (items.length > 1) {
                    const lastItem = items[items.length - 1];
                    const simpleCrumb = document.createElement('nav');
                    simpleCrumb.setAttribute('aria-label', 'Breadcrumb');
                    simpleCrumb.className = 'd-md-none mt-2';
                    simpleCrumb.innerHTML = '<small class="text-muted">Você está em: </small><span class="text-primary">' + lastItem.textContent + '</span>';
                    breadcrumb.parentNode.insertBefore(simpleCrumb, breadcrumb.nextSibling);
                }
            }
        });
    }

    // ============================================
    // SUPORTE A MÓDULOS ESPECÍFICOS
    // ============================================
    
    // Dashboard
    window.initDashboardMobile = function() {
        if (!isMobile) return;

        // Make stat cards tappable
        document.querySelectorAll('.stat-card').forEach(function(card) {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function() {
                const link = card.querySelector('a');
                if (link) link.click();
            });
        });
    };

    // Financial Module
    window.initFinancialMobile = function() {
        if (!isMobile) return;

        // Add color coding to values
        document.querySelectorAll('.table td').forEach(function(cell) {
            if (cell.textContent.includes('R$')) {
                if (cell.textContent.includes('-')) {
                    cell.style.color = '#dc3545';
                    cell.style.fontWeight = '600';
                } else {
                    cell.style.color = '#198754';
                    cell.style.fontWeight = '600';
                }
            }
        });
    };

    // Agenda Module
    window.initAgendaMobile = function() {
        if (!isMobile) return;

        // If using FullCalendar, switch to list view
        const calendarEl = document.getElementById('calendar');
        if (calendarEl && typeof FullCalendar !== 'undefined') {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'listWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                buttonText: {
                    today: 'Hoje',
                    prev: '<',
                    next: '>'
                }
            });
            calendar.render();
        }
    };

    // ============================================
    // FUNÇÕES UTILITÁRIAS
    // ============================================

    // Show toast notification
    window.showMobileToast = function(title, message, type) {
        showToast(title, message, type);
    };

    // Loading overlay
    window.showMobileLoading = function(message) {
        const existing = document.querySelector('.mobile-loading-overlay');
        if (existing) return;

        const overlay = document.createElement('div');
        overlay.className = 'mobile-loading-overlay';
        overlay.innerHTML = '<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;overflow:hidden;"><div style="background:white;padding:2rem;border-radius:1rem;text-align:center;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div><p style="margin-top:1rem;margin-bottom:0;">' + (message || 'Carregando...') + '</p></div></div>';
        document.body.appendChild(overlay);
        // Não bloquear overflow do body, o overlay já cobre a tela
    };

    window.hideMobileLoading = function() {
        const overlay = document.querySelector('.mobile-loading-overlay');
        if (overlay) {
            overlay.remove();
            // Não manipular overflow do body, deixar o CSS controlar
        }
    };

    // Confirm dialog mobile friendly
    window.confirmMobile = function(message, callback) {
        const modalHTML = '<div class="modal fade" id="mobileConfirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center py-4"><p class="mb-4">' + message + '</p><div class="d-flex gap-2 justify-content-center"><button type="button" class="btn btn-secondary flex-fill" data-dismiss="modal" id="confirmCancel">Cancelar</button><button type="button" class="btn btn-primary flex-fill" id="confirmOk">Confirmar</button></div></div></div></div></div>';
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = modalHTML;
        document.body.appendChild(tempDiv.firstElementChild);

        const modal = document.getElementById('mobileConfirmModal');
        const modalInstance = new bootstrap.Modal(modal);

        modal.addEventListener('hidden.bs.modal', function() {
            modal.remove();
        });

        document.getElementById('confirmOk').addEventListener('click', function() {
            modalInstance.hide();
            if (callback) callback(true);
        });

        document.getElementById('confirmCancel').addEventListener('click', function() {
            modalInstance.hide();
            if (callback) callback(false);
        });

        modalInstance.show();
    };

    console.log('Titanium Gym Mobile Enhancements Loaded');
})();
