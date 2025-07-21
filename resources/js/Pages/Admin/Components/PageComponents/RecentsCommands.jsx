export default function RecentsCommands()
{
    return    <div class="col-md-6 col-xxl-6 order-0 order-xxl-6">
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title mb-0">
                <h5 class="m-0 me-2">
                    Commandes récentes
                </h5>
            </div>
            <div class="dropdown">
                <button
                    class="btn btn-text-secondary rounded-pill text-muted border-0 p-1"
                    type="button"
                    id="ordersCountries"
                    data-bs-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <i class="ri-more-2-line ri-20px"></i>
                </button>
                <div
                    class="dropdown-menu dropdown-menu-end"
                    aria-labelledby="ordersCountries"
                >
                    <a
                        class="dropdown-item"
                        href="javascript:void(0);"
                    >
                        Voir tout
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="nav-align-top">
                <ul
                    class="nav nav-tabs nav-fill"
                    role="tablist"
                >
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link active"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#navs-justified-new"
                            aria-controls="navs-justified-new"
                            aria-selected="true"
                        >
                            En cours
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#navs-justified-link-preparing"
                            aria-controls="navs-justified-link-preparing"
                            aria-selected="false"
                        >
                            Terminées
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#navs-justified-link-shipping"
                            aria-controls="navs-justified-link-shipping"
                            aria-selected="false"
                        >
                            Annulées
                        </button>
                    </li>
                </ul>
                <div class="tab-content border-0 pb-0 px-6 mx-1">
                    <div
                        class="tab-pane fade show active"
                        id="navs-justified-new"
                        role="tabpanel"
                    >
                        <ul class="timeline mb-0">
                            <li class="timeline-item ps-6 border-transparent">
                                <span class="timeline-indicator-advanced text-primary border-0 shadow-none">
                                    <i class="ri-map-pin-line ri-20px"></i>
                                </span>
                                <div class="timeline-event ps-1">
                                    <div class="timeline-header">
                                        <small class="text-primary text-uppercase">
                                            Client
                                            1
                                        </small>
                                    </div>
                                    <h6 class="my-50">
                                        Ville :
                                        Garoua |
                                        Adresse
                                        : Odza 1{" "}
                                    </h6>
                                    <p class="mb-0 small">
                                        ¨Prix :
                                        20 000
                                        FCFA
                                    </p>
                                </div>
                            </li>
                        </ul>
                        <div class="border-1 border-light border-top border-dashed mb-2"></div>
                        <ul class="timeline mb-0">
                            <li class="timeline-item ps-6 border-transparent">
                                <span class="timeline-indicator-advanced text-primary border-0 shadow-none">
                                    <i class="ri-map-pin-line ri-20px"></i>
                                </span>
                                <div class="timeline-event ps-1">
                                    <div class="timeline-header">
                                        <small class="text-primary text-uppercase">
                                            Client
                                            1
                                        </small>
                                    </div>
                                    <h6 class="my-50">
                                        Ville :
                                        Garoua |
                                        Adresse
                                        : Odza 1{" "}
                                    </h6>
                                    <p class="mb-0 small">
                                        ¨Prix :
                                        20 000
                                        FCFA
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div
                        class="tab-pane fade"
                        id="navs-justified-link-preparing"
                        role="tabpanel"
                    >
                        <ul class="timeline mb-0">
                            <li class="timeline-item ps-6 border-left-dashed">
                                <span class="timeline-indicator-advanced text-success border-0 shadow-none">
                                    <i class="ri-checkbox-circle-line ri-20px"></i>
                                </span>
                                <div class="timeline-event ps-1">
                                    <div class="timeline-header">
                                        <small class="text-success text-uppercase">
                                            Client
                                            1
                                        </small>
                                    </div>
                                    <h6 class="my-50">
                                        Ville :
                                        Garoua |
                                        Adresse
                                        : Odza 1{" "}
                                    </h6>
                                    <p class="mb-0 small">
                                        ¨Prix :
                                        20 000
                                        FCFA
                                    </p>
                                </div>
                            </li>
                        </ul>
                        <div class="border-1 border-light border-top border-dashed mb-2 "></div>
                        <ul class="timeline mb-0">
                            <li class="timeline-item ps-6 border-left-dashed">
                                <span class="timeline-indicator-advanced text-success border-0 shadow-none">
                                    <i class="ri-checkbox-circle-line ri-20px"></i>
                                </span>
                                <div class="timeline-event ps-1">
                                    <div class="timeline-header">
                                        <small class="text-success text-uppercase">
                                            Client
                                            1
                                        </small>
                                    </div>
                                    <h6 class="my-50">
                                        Ville :
                                        Garoua |
                                        Adresse
                                        : Odza 1{" "}
                                    </h6>
                                    <p class="mb-0 small">
                                        ¨Prix :
                                        20 000
                                        FCFA
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

}