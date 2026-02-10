// script.js

let urgentSubs = []; 

function checkRenewals(subscriptions) {
    urgentSubs = []; 
    const today = new Date();
    
    subscriptions.forEach(sub => {
        const due = new Date(sub.next_due_date);
        const diffTime = due - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 

        if(diffDays <= 3 && diffDays >= 0) {
            urgentSubs.push({
                name: sub.service_name,
                price: sub.price,
                days: diffDays
            });
        }
    });

    updateBellUI();
}

function updateBellUI() {
    const badge = document.getElementById('notification-badge');
    const bellIcon = document.getElementById('bell-icon');

    if(urgentSubs.length > 0) {
        badge.style.display = 'block';
        badge.innerText = urgentSubs.length;
        bellIcon.classList.add('fa-shake');

        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true,
            didOpen: (toast) => { toast.addEventListener('mouseenter', Swal.stopTimer); toast.addEventListener('mouseleave', Swal.resumeTimer); }
        });
        Toast.fire({ icon: 'warning', title: `Heads up! You have ${urgentSubs.length} upcoming bills.` });
    } else {
        badge.style.display = 'none';
        bellIcon.classList.remove('fa-shake');
    }
}

function showNotificationList() {
    if(urgentSubs.length === 0) {
        Swal.fire({ icon: 'success', title: 'All caught up!', text: 'No subscriptions due soon.', confirmButtonColor: '#667eea' });
        return;
    }

    let listHtml = '<ul style="text-align: left; list-style: none; padding: 0;">';
    urgentSubs.forEach(item => {
        let dayText = item.days === 0 ? "Today!" : `in ${item.days} days`;
        let cleanName = item.name.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
        let logoUrl = `https://www.google.com/s2/favicons?domain=${cleanName}.com&sz=64`;

        // USE THE GLOBAL CURRENCY SYMBOL HERE
        listHtml += `
            <li style="display: flex; align-items: center; background: #fff; padding: 10px; margin-bottom: 8px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid #ff4757;">
                <img src="${logoUrl}" style="width: 35px; height: 35px; border-radius: 50%; margin-right: 10px; object-fit: contain;">
                <div style="flex-grow: 1;">
                    <strong>${item.name}</strong> 
                    <br><small class="text-muted">Due: ${dayText}</small>
                </div>
                <span style="color: #ff4757; font-weight: bold;">${CURRENCY_SYMBOL}${item.price}</span>
            </li>`;
    });
    listHtml += '</ul>';

    Swal.fire({ title: '⚠️ Upcoming Renewals', html: listHtml, showCloseButton: true, focusConfirm: false, confirmButtonText: 'Got it!', confirmButtonColor: '#667eea' });
}
