
function adjustLayout() {
    const mainContent = document.querySelector('.main-content');
    const sidebar = document.querySelector('.sidebar');

    if (window.innerWidth <= 992) {
        mainContent.classList.add('col-12');
        mainContent.classList.remove('col-md-10');
    } else {
        mainContent.classList.remove('col-12');
        mainContent.classList.add('col-md-10');
        sidebar.classList.remove('show');
    }
}

function toggleOverlay() {
    const sidebar = document.querySelector('.sidebar');
    const existingOverlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar.classList.contains('show') && window.innerWidth <= 992) {
        if (!existingOverlay) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.4)';
            overlay.style.zIndex = '1020';
            overlay.style.transition = 'opacity 0.3s ease';
            overlay.style.opacity = '0';
            document.body.appendChild(overlay);
            
            setTimeout(() => {
                overlay.style.opacity = '1';
            }, 10);
            
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                this.style.opacity = '0';
                setTimeout(() => {
                    this.remove();
                }, 300);
            });
        }
    } else if (existingOverlay) {
        existingOverlay.style.opacity = '0';
        setTimeout(() => {
            existingOverlay.remove();
        }, 300);
    }
}

export { adjustLayout, toggleOverlay };
