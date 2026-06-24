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
        var hours = prompt('请输入分享链接有效期（小时）：\n\n留空或输入0 = 永久有效\n输入24 = 24小时后过期', '');
        if (hours === null) return;
        
        var expireType = (hours === '' || hours === '0') ? 'permanent' : 'limited';
        var expireHours = parseInt(hours) || 0;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'create_share_link.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var result = JSON.parse(xhr.responseText);
                if (result.success) {
                    // 构建完整URL（自动检测子目录）
                    var path = window.location.pathname;
                    var basePath = path.substring(0, path.indexOf('/admin/'));
                    var url = window.location.origin + basePath + '/share.php?token=' + result.token;
                    
                    // 尝试复制到剪贴板
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function() {
                            alert('分享链接已创建并复制到剪贴板！\n\n' + url);
                        }).catch(function() {
                            // 备用方案
                            fallbackCopy(url);
                        });
                    } else {
                        fallbackCopy(url);
                    }
                } else {
                    alert('创建失败：' + result.error);
                }
            }
        };
        xhr.send('bill_id=' + billId + '&expire_type=' + expireType + '&expire_hours=' + expireHours);
    }
    
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('分享链接已复制到剪贴板！\n\n' + text);
        } catch(e) {
            prompt('请手动复制链接：', text);
        }
        document.body.removeChild(textarea);
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
    
    // 账单页面：选择合同自动填充上月数据
    var contractSelect = document.getElementById('contractSelect');
    if (contractSelect) {
        contractSelect.addEventListener('change', function() {
            var contractId = this.value;
            if (contractId) {
                // 获取上月账单数据
                fetch('get_last_bill.php?contract_id=' + contractId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('water_start').value = data.water_end || 0;
                            document.getElementById('elec_start').value = data.elec_end || 0;
                        }
                    });
            }
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
