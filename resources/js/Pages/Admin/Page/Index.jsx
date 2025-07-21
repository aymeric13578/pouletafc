import { useState } from "react";
import Footer from "../Components/Footer";
import Menu from "../Components/Menu";
import NavBar from "../Components/NavBar";
import BestProductsComponents from "../Components/PageComponents/BestProductsComponents";
import RecentsCommands from "../Components/PageComponents/RecentsCommands";
import Table from "../Components/PageComponents/Table";
import BlocHeader from "../Components/PageComponents/BlocHeader";

export default function Index({users}) {
    const [transactionsTable, setTransactionsTable] = useState([
        {
            CODE: "aaaa",
            CLIENT: "alphonse",
            PRIX: 200,
            VILLE: "yaoundé",
            LIVREUR: "loic",
            STATUT: "",
        },
        {
            CODE: "aaaa",
            CLIENT: "alphonse",
            PRIX: 200,
            VILLE: "yaoundé",
            LIVREUR: "loic",
            STATUT: "",
        },
    ]);

    const elementsHeadTable = [
        "CODE",
        "CLIENT",
        "PRIX",
        "VILLE",
        "LIVREUR",
        "STATUT",
    ];

    return (
        <div className="layout-wrapper layout-content-navbar  ">
            <div className="layout-container">
                <Menu />

                <div class="layout-page">
                    <NavBar />

                    <div class="content-wrapper">
                        <div class="container-xxl flex-grow-1 container-p-y">
                            <div class="row g-6">


                                <BlocHeader icone="ri-car-line ri-24px" title="en cours de livraisons" number="8" label="bg-label-primary" />
                                <BlocHeader icone="ri-alert-line ri-24px" title="Utilisateurs" number="8" label="bg-label-warning" />
                                <BlocHeader icone="ri-route-line ri-24px" title="Commandes effectuées" number="8" label="bg-label-danger" />
                                <BlocHeader icone="ri-time-line ri-24px" title="Livreurs disponibles" number="8" label="bg-label-info" />
                                <BestProductsComponents />
                                <RecentsCommands />
                            
                                <Table
                                    title="Liste des transactions"
                                 
                                    elementsHeadTable={elementsHeadTable}
                                >
                                    {transactionsTable.map(e =>
                                        <tr>
                                            <td>  {e.CODE} </td>
                                            <td>  {e.CLIENT} </td>
                                            <td>  {e.PRIX} </td>
                                            <td>  {e.VILLE} </td>
                                            <td>  {e.LIVREUR} </td>
                                            <td>  {e.STATUT} </td>


                                        </tr>
                                    )}


                                </Table>
                            </div>
                        </div>

                        <Footer />
                        <div class="content-backdrop fade"></div>
                    </div>
                </div>
            </div>

            <div className="layout-overlay layout-menu-toggle"></div>

            <div className="drag-target"></div>
        </div>
    );
}
