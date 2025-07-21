import { Link } from '@inertiajs/react'


export default function Menu() {
    return <aside
        id="layout-menu"
        class="layout-menu menu-vertical menu bg-menu-theme"
    >
        <div class="app-brand demo ">


            <a href="index.html" >
                <img
                    src="/publicAdmin/img/blue.png"
                    style={{ width: "50px;" }}
                    alt=""
                />
            </a>

            <a
                href="javascript:void(0);"
                class="layout-menu-toggle menu-link text-large ms-auto"
            >
                <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M8.47365 11.7183C8.11707 12.0749 8.11707 12.6531 8.47365 13.0097L12.071 16.607C12.4615 16.9975 12.4615 17.6305 12.071 18.021C11.6805 18.4115 11.0475 18.4115 10.657 18.021L5.83009 13.1941C5.37164 12.7356 5.37164 11.9924 5.83009 11.5339L10.657 6.707C11.0475 6.31653 11.6805 6.31653 12.071 6.707C12.4615 7.09747 12.4615 7.73053 12.071 8.121L8.47365 11.7183Z"
                        fill-opacity="0.9"
                    />
                    <path
                        d="M14.3584 11.8336C14.0654 12.1266 14.0654 12.6014 14.3584 12.8944L18.071 16.607C18.4615 16.9975 18.4615 17.6305 18.071 18.021C17.6805 18.4115 17.0475 18.4115 16.657 18.021L11.6819 13.0459C11.3053 12.6693 11.3053 12.0587 11.6819 11.6821L16.657 6.707C17.0475 6.31653 17.6805 6.31653 18.071 6.707C18.4615 7.09747 18.4615 7.73053 18.071 8.121L14.3584 11.8336Z"
                        fill-opacity="0.4"
                    />
                </svg>
            </a>
        </div>
        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
            <li class="menu-header mt-5">
                <span
                    class="menu-header-text"
                    data-i18nnn="Apps & Pages"
                >
                    MENU
                </span>
            </li>
            <li class="menu-item">

                <Link className="menu-link" href="/admin">  <i class="menu-icon tf-icons ri-home-smile-line"></i>
                    <div data-i18nnn="Dashboards">Accueil</div></Link>

            </li>
            <li class="menu-header mt-5">
                <span
                    class="menu-header-text"
                    data-i18nnn="Apps & Pages"
                >
                    Ressource humaines
                </span>
            </li>
            
            <li class="menu-item">

                <Link className="menu-link" href="/ListUsers">
                    <i class="menu-icon tf-icons ri-user-line"></i>
                    <div data-i18nnn="Email">Utilisateurs</div>
                </Link>

            </li>
            <li class="menu-item">
                <Link href="/ListCustomers" class="menu-link ">
                    <i class="menu-icon tf-icons ri-layout-2-line"></i>
                    <div data-i18nnn="Layouts">Clients</div>
                </Link>
            </li>
            <li class="menu-item">
                <Link
                    href="/ListAgents"
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-truck-line"></i>
                    <div data-i18nnn="Front Pages">
                        Gestion des agents
                    </div>
                </Link>
               
            </li>
            <li class="menu-item">
                <Link
                    href="/ListMarchands"
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-store-2-line"></i>
                    <div data-i18nnn="Front Pages">
                        Gestion des marchands
                    </div>
                </Link>
                
            </li>
            <li class="menu-header mt-5">
                <span
                    class="menu-header-text"
                    data-i18nnn="Apps & Pages"
                >
                    Section produits
                </span>
            </li>
            /
            <li class="menu-item">
                <Link
                    href="/ListCommandes"
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-shopping-cart-2-line"></i>
                    <div data-i18nnn="Front Pages">Commande</div>
                </Link>
                
            </li>
            <li class="menu-item">
                <Link
                    href="/ListProducts"
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-shopping-bag-2-line"></i>
                    <div>Produits</div>
                </Link>
              
            </li>
            <li class="menu-item">
                <Link
                    href="/ListShops"
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-store-3-line"></i>
                    <div data-i18nnn="Front Pages">Boutique</div>
                </Link>
             
            </li>
            <li class="menu-item">
                <Link
                    href="/ListCategories"
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-file-copy-line"></i>
                    <div data-i18nnn="Front Pages">Catégories</div>
                </Link>
               
            </li>
            <li class="menu-item">
                <a
                    href="/ListSubCategories"
                    class="menu-link"
                >
                    <i class="menu-icon tf-icons ri-file-copy-line"></i>
                    <div data-i18nnn="Front Pages">
                        Sous-catégories
                    </div>
                </a>
              
            </li>
            <li class="menu-item">
                <Link
                    href=""
                    class="menu-link "
                >
                    <i class="menu-icon tf-icons ri-store-2-line"></i>
                    <div data-i18nnn="Front Pages">Fournisseurs</div>
                </Link>
             
            </li>
            <li class="menu-header mt-5">
                <span
                    class="menu-header-text"
                    data-i18nnn="Apps & Pages"
                >
                    Section rapports
                </span>
            </li>
            <li class="menu-item">
                <a href="transaction.html" class="menu-link ">
                    <i class="menu-icon tf-icons ri-exchange-dollar-line"></i>
                    <div data-i18nnn="Front Pages">
                        Transactions
                    </div>
                </a>
            </li>
            <li class="menu-item">
                <a
                    href="javascript:void(0);"
                    class="menu-link menu-toggle"
                >
                    <i class="menu-icon tf-icons ri-exchange-funds-line"></i>

                    <div data-i18nnn="Front Pages">
                        Statistiques
                    </div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a
                            href="statistique_global.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Landing">Global</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a
                            href="statistique_livreur.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Pricing">Livreur</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a
                            href="statistique_marchandafc.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Pricing">
                                Marchand AFC
                            </div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a
                            href="statistique_marchandind.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Pricing">
                                Marchand indépendant
                            </div>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="menu-item">
                <a
                    href="javascript:void(0);"
                    class="menu-link menu-toggle"
                >
                    <i class="menu-icon tf-icons ri-money-dollar-circle-line"></i>

                    <div data-i18nnn="Front Pages">Finances</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a
                            href="finance_global.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Landing">Global</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a
                            href="finance_livreur.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Pricing">Livreur</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a
                            href="finance_marchandafc.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Pricing">
                                Marchand AFC
                            </div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a
                            href="finance_marchandind.html"
                            class="menu-link"
                            target="_blank"
                        >
                            <div data-i18nnn="Pricing">
                                Marchand indépendant
                            </div>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="menu-item">
                <a href="notification.html" class="menu-link ">
                    <i class="menu-icon tf-icons ri-notification-line"></i>
                    <div data-i18nnn="Front Pages">
                        Notification
                    </div>
                    <div class="badge bg-danger rounded-pill ms-auto">
                        5
                    </div>
                </a>
            </li>
            <li class="menu-item">
                <a href="message.html" class="menu-link">
                    <i class="menu-icon tf-icons ri-mail-open-line"></i>
                    <div data-i18nnn="Email">Message</div>
                </a>
            </li>
            <li class="menu-item">
                <a href="parametre.html" class="menu-link ">
                    <i class="menu-icon tf-icons ri-file-copy-line"></i>
                    <div data-i18nnn="Front Pages">Paramètres</div>
                </a>
            </li>
            /
        </ul>
    </aside>
}