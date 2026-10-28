// Shared UI utilities — loaded by layout.php
function openModal(id) { const modal=document.getElementById(id);if(modal?.hasAttribute('data-portal-dialog')&&window.PortalDialogs)window.PortalDialogs.open(modal);else modal?.classList.remove('hidden'); }
function closeModal(id) { const modal=document.getElementById(id);if(modal?.hasAttribute('data-portal-dialog')&&window.PortalDialogs)window.PortalDialogs.close(modal);else modal?.classList.add('hidden'); }

function switchTab(tab, tabs, options) {
    options = options || {};
    var prefix = options.prefix || 'panel-';
    var tabPrefix = options.tabPrefix || 'tab-';
    var activeClass = options.activeClass || 'px-3 py-1.5 text-sm rounded-md bg-primary text-white';
    var inactiveClass = options.inactiveClass || 'px-3 py-1.5 text-sm rounded-md text-gray-600 hover:bg-gray-200';

    // Backwards-compatible: if tabs not provided, auto-detect from DOM.
    if (!Array.isArray(tabs) || tabs.length === 0) {
        tabs = Array.from(document.querySelectorAll('[id^="' + prefix + '"]'))
            .map(function(el) { return el.id.slice(prefix.length); });
    }

    tabs.forEach(function(t) {
        var panel = document.getElementById(prefix + t);
        if (panel) panel.classList.toggle('hidden', t !== tab);
        var btn = document.getElementById(tabPrefix + t);
        if (btn) btn.className = t === tab ? activeClass : inactiveClass;
    });
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('bg-black/50') || e.target.classList.contains('bg-black\\/50')) {
        e.target.classList.add('hidden');
    }
});

function officeAlert(...args){return window.PortalDialogs?window.PortalDialogs.fire(...args):Swal.fire(...args);}
