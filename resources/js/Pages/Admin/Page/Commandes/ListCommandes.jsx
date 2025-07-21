import { useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";

export default function ListCommandes() {


  const [commandesTable, setCommandesTable] = useState([
    {
      CODE: "aaaa",
      PANIER: "alphonse",
      VILLE: "Yaoundé",
      INITIE_PAR: "alphonse",
      LIVREUR: "alphonse",
      PRIX: 3000,
      STATUS: "validé",
      ACTIONS:""
    },
    {
      CODE: "aaaa",
      PANIER: "alphonse",
      VILLE: "Yaoundé",
      INITIE_PAR: "alphonse",
      LIVREUR: "alphonse",
      PRIX: 3000,
      STATUS: "validé",
      ACTIONS:""
    },
  ]);

  const elementsHeadTable = [
   "CODE",
   "PANIER",
   "VILLE",
   "INITIE_PAR",
   "LIVREUR",
   "PRIX",
   "STATUS",
   "ACTIONS"
  ];



  return (
    <div className="layout-wrapper layout-content-navbar  ">
      <div className="layout-container">
        <Menu />

        <div class="layout-page">
          <NavBar />

          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">


              <Table
                title="Liste des commandes"
                elementsHeadTable={elementsHeadTable}
              >

                {commandesTable.map(e =>
                  <tr>
                    <td>  {e.CODE} </td>
                    <td>  {e.PANIER} </td>
                    <td>  {e.VILLE} </td>
                    <td>  {e.INITIE_PAR} </td>
                    <td>  {e.LIVREUR} </td>
                    <td>  {e.PRIX} </td>
                    <td>  {e.STATUS} </td>
                    <td>  {e.ACTIONS} </td>



                  </tr>
                )}



              </Table>

              {/* <hr class="my-12" /> */}




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
