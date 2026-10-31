    </div><!-- /.admin-content -->
</div><!-- /.admin-main-wrap -->

<script>
(function() {
    var toggle = document.getElementById('adminMenuToggle');
    var sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
})();
</script>
</body>
</html>
