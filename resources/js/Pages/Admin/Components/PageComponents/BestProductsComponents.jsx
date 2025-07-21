export default function BestProductsComponents()
{
    return <>
    
    <div class="col-xxl-6">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    Top 5
                                                </h5>
                                                <p class="card-subtitle mb-0">
                                                    Meilleurs produits
                                                </p>
                                            </div>
                                            <div class="dropdown">
                                                <button
                                                    class="btn btn-text-secondary rounded-pill text-muted border-0 p-1"
                                                    type="button"
                                                    id="earningReportsTabsId"
                                                    data-bs-toggle="dropdown"
                                                    aria-haspopup="true"
                                                    aria-expanded="false"
                                                >
                                                    <i class="ri-more-2-line ri-20px"></i>
                                                </button>
                                                <div
                                                    class="dropdown-menu dropdown-menu-end"
                                                    aria-labelledby="earningReportsTabsId"
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

                                        <div class="tab-content p-0">
                                            <div
                                                class="tab-pane fade show active"
                                                id="navs-orders-id-1"
                                                role="tabpanel"
                                            >
                                                <div class="table-responsive text-nowrap">
                                                    <table class="table border-top">
                                                        <thead>
                                                            <tr>
                                                                <th class="bg-transparent border-bottom">
                                                                    Image
                                                                </th>
                                                                <th class="bg-transparent border-bottom">
                                                                    Nom
                                                                </th>
                                                                <th class="text-end bg-transparent border-bottom">
                                                                    Boutiques
                                                                </th>
                                                                <th class="text-end bg-transparent border-bottom">
                                                                    Ville
                                                                </th>
                                                                <th class="text-end bg-transparent border-bottom">
                                                                    Nbr d'achat
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="table-border-bottom-0">
                                                            <tr>
                                                                <td>
                                                                    <img
                                                                        src="assets/img/products/samsung-s22.png"
                                                                        alt="Mobile"
                                                                        width="34"
                                                                        height="34"
                                                                        class="rounded"
                                                                    />
                                                                </td>
                                                                <td>
                                                                    Samsung s22
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="badge bg-label-primary rounded-pill">
                                                                        glotelho
                                                                    </div>
                                                                </td>
                                                                <td class="text-end fw-medium">
                                                                    Garoua
                                                                </td>
                                                                <td class=" fw-medium text-end">
                                                                    5
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <img
                                                                        src="assets/img/products/apple-iPhone-13-pro.png"
                                                                        alt="Mobile"
                                                                        width="34"
                                                                        height="34"
                                                                        class="rounded"
                                                                    />
                                                                </td>
                                                                <td>
                                                                    iPhone 14
                                                                    Pro
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="badge bg-label-success rounded-pill">
                                                                        samsung
                                                                    </div>
                                                                </td>
                                                                <td class="text-end fw-medium">
                                                                    Garoua
                                                                </td>
                                                                <td class=" fw-medium text-end">
                                                                    5
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <img
                                                                        src="assets/img/products/oneplus-9-pro.png"
                                                                        alt="Mobile"
                                                                        width="34"
                                                                        height="34"
                                                                        class="rounded"
                                                                    />
                                                                </td>
                                                                <td>
                                                                    Oneplus 9
                                                                    Pro
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="badge bg-label-warning rounded-pill">
                                                                        oneplus
                                                                    </div>
                                                                </td>
                                                                <td class="text-end fw-medium">
                                                                    Garoua
                                                                </td>
                                                                <td class=" fw-medium text-end">
                                                                    2
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <img
                                                                        src="assets/img/products/google-pixel-6.png"
                                                                        alt="Mobile"
                                                                        width="34"
                                                                        height="34"
                                                                        class="rounded"
                                                                    />
                                                                </td>
                                                                <td>
                                                                    Google Pixel
                                                                    6
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="badge bg-label-success rounded-pill">
                                                                        Google
                                                                    </div>
                                                                </td>
                                                                <td class="text-end fw-medium">
                                                                    Garoua
                                                                </td>
                                                                <td class=" fw-medium text-end">
                                                                    1
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
    
    </>
}