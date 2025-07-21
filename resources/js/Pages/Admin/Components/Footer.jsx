export default function Footer()
{
    return     <footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
            <div class="text-body mb-2 mb-md-0">
                ©
                <script>
                    document.write(new
                    Date().getFullYear())
                </script>
                , made with{" "}
                <span class="text-danger">
                    <i class="tf-icons ri-heart-fill"></i>
                </span>{" "}
                by{" "}
                <a
                    href=""
                    target="_blank"
                    class="footer-link"
                >
                    OBM{" "}
                </a>
            </div>
            <div class="d-none d-lg-inline-block">
                <a
                    href=""
                    class="footer-link me-4"
                    target="_blank"
                >
                    License
                </a>
                <a
                    href=""
                    target="_blank"
                    class="footer-link me-4"
                >
                    More Themes
                </a>

                <a
                    href=""
                    target="_blank"
                    class="footer-link me-4"
                >
                    Documentation
                </a>

                <a
                    href=""
                    target="_blank"
                    class="footer-link d-none d-sm-inline-block"
                >
                    Support
                </a>
            </div>
        </div>
    </div>
</footer>

}