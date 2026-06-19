    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    function toggleSidebar() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (sidebar && overlay) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
    }
    
    // 分享账单功能
    function shareBill(billId, roomNumber, billMonth, tenantName) {
        var currentPath = window.location.pathname;
        var basePath = currentPath.substring(0, currentPath.lastIndexOf('/admin/') + 1);
        var shareUrl = window.location.origin + basePath + 'bill_view.php?id=' + billId;
        var shareText = tenantName + ' 的账单 - ' + roomNumber + ' ' + billMonth;
        
        if (navigator.share) {
            navigator.share({
                title: shareText,
                text: '请查看您的账单：' + roomNumber + ' ' + billMonth,
                url: shareUrl
            }).then(function() {
                console.log('分享成功');
            }).catch(function(err) {
                console.log('分享取消');
            });
        } else {
            var tempInput = document.createElement('input');
            tempInput.value = shareUrl;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            alert('账单链接已复制到剪贴板！\n\n' + shareUrl + '\n\n可以发送给租客查看。');
        }
    }
    
    // 合同页面：选择房间自动填充租金
    var roomSelect = document.getElementById('roomSelect');
    var rentInput = document.getElementById('monthlyRent');
    if (roomSelect && rentInput) {
        roomSelect.addEventListener('change', function() {
            var option = this.options[this.selectedIndex];
            var price = option.getAttribute('data-price');
            if (price) rentInput.value = price;
        });
    }
    
    // 账单页面：计算预估费用
    function calcEstimates() {
        var wp = parseFloat(document.querySelector('input[name="water_price"]')?.value) || 0;
        var ws = parseFloat(document.getElementById('water_start')?.value) || 0;
        var we = parseFloat(document.getElementById('water_end')?.value) || 0;
        var waterEst = document.getElementById('water_est');
        if (waterEst) waterEst.value = '¥' + ((we - ws) * wp).toFixed(2);
        
        var ep = parseFloat(document.querySelector('input[name="elec_price"]')?.value) || 0;
        var es = parseFloat(document.getElementById('elec_start')?.value) || 0;
        var ee = parseFloat(document.getElementById('elec_end')?.value) || 0;
        var elecEst = document.getElementById('elec_est');
        if (elecEst) elecEst.value = '¥' + ((ee - es) * ep).toFixed(2);
    }
    
    ['water_start','water_end','elec_start','elec_end'].forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', calcEstimates);
    });
    
    var wpInput = document.querySelector('input[name="water_price"]');
    var epInput = document.querySelector('input[name="elec_price"]');
    if (wpInput) wpInput.addEventListener('input', calcEstimates);
    if (epInput) epInput.addEventListener('input', calcEstimates);
    </script>
</body>
</html>
