import { useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";

export default function ListCustomers() {


  const [customersTable, setCustomersTable] = useState([
    {
      MATRICULE: "aaaa",
      NOM: "alphonse",
      NBR_ACHAT: 200,
      DERNIER_ACHAT: "yaoundé",
      STATUT: "loic",
      ACTION: "",
    },
    {
      MATRICULE: "aaaa",
      NOM: "alphonse",
      NBR_ACHAT: 200,
      DERNIER_ACHAT: "yaoundé",
      STATUT: "loic",
      ACTION: "",
    },
  ]);

  const elementsHeadTable = [
    "MATRICULE",
    "NOM",
    "NBR_ACHAT",
    "DERNIER_ACHAT",
    "STATUT",
    "ACTION",
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
                title="Liste des clients"
                elementsHeadTable={elementsHeadTable}
              >

                {customersTable.map(e =>
                  <tr>
                    <td>  {e.MATRICULE} </td>
                    <td>  {e.NOM} </td>
                    <td>  {e.NBR_ACHAT} </td>
                    <td>  {e.DERNIER_ACHAT} </td>
                    <td>  {e.STATUT} </td>
                    <td>  {e.ACTION} </td>
                  </tr>
                )}



              </Table>

              <hr class="my-12" />




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
