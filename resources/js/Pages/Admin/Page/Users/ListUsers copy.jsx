import { useEffect, useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";

export default function ListUsers({ users }) {

  const [usersTable, setUsersTable] = useState([
    {
      MATRICULE: "aaaa",
      NOM: "alphonse",
      EMAIL: "alphonsemvele95@gmail.com",
      CONTACT: "yaoundé",
      VILLE: "loic",
      ROLE: "admin",
      STATUT: "",
      ACTIONS: ""
    },
    {
      MATRICULE: "aaaa",
      NOM: "alphonse",
      EMAIL: "alphonsemvele95@gmail.com",
      CONTACT: "yaoundé",
      VILLE: "loic",
      ROLE: "admin",
      STATUT: "",
      ACTIONS: ""
    },
  ]);

  const elementsHeadTable = [
    "MATRICULE",
    "NOM",
    "EMAIL",
    "CONTACT",
    "VILLE",
    "ROLE",
    "STATUT",
    "ACTIONS",


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
                title="Liste des utilisateurs"
                elementsHeadTable={elementsHeadTable}
              >

                {users.map(e =>
                  <tr>
                    <td>  {e.ref} </td>
                    <td>  {e.name} </td>
                    <td>  {e.email} </td>
                    <td>  {e.phone} </td>
                    <td>  {e.city} </td>
                    <td>  {e.role} </td>
                    <td>  {e.status} </td>
                    <td>   </td>


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
