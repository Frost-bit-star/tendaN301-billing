<!-- components/footer.php -->
<style>
/* ===== Footer Styling (Centered, Black & White) ===== */
.main-footer {
    background: #000;          /* pure black */
    color: #fff;               /* white text */
    padding: 1rem;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

    display: flex;
    flex-direction: column;    /* stack items */
    justify-content: center;
    align-items: center;       /* center horizontally */
    text-align: center;        /* center text */

    border-top: 1px solid #fff;
}

/* Remove colored accents */
.main-footer strong {
    color: #fff;
}

/* Links (if any) */
.main-footer a {
    color: #fff;
    text-decoration: underline;
}

.main-footer a:hover {
    color: #ccc;
}

/* Control sidebar */
.control-sidebar {
    background: #000;
}
</style>

<!-- Control sidebar -->
<aside class="control-sidebar control-sidebar-dark"></aside>

<!-- Footer -->
<footer class="main-footer">
    <strong>&copy; <?= date('Y') ?> jasiri billing.</strong>
    <span>All rights reserved.</span>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
