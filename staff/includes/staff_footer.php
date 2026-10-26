            </main>
        </div> <!-- End staff-main-wrapper -->
    </div> <!-- End staff-layout -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.staff-sidebar');
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.toggle('open');
                    } else {
                        sidebar.classList.toggle('collapsed');
                    }
                });
            }
        });
    </script>
</body>
</html>
