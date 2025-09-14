
<!-- Footer -->
<footer>
    &copy; 2025 SWKS Management System. All Rights Reserved.
</footer>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.addEventListener("scroll", function () {
        const header = document.querySelector("header");
        if (window.scrollY > 10) {
            header.classList.add("scrolled");
        } else {
            header.classList.remove("scrolled");
        }
    });
</script>

</body>
</html>
