import { useEffect, useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";
import { Column } from 'primereact/column';

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
                elements = {users}
              >

    <Column field="ref" filterField="ref"  style={{ minWidth: '12rem' }} header="REFERENCE" ></Column>
    <Column field="name"  filterField="name" style={{ minWidth: '12rem' }}  header="NOM"></Column>
    <Column field="email"  filterField="email" style={{ minWidth: '12rem' }} header="EMAIL" ></Column>
    <Column field="phone"  filterField="phone" style={{ minWidth: '12rem' }} header="CONTACT" ></Column>
    <Column field="city"  filterField="city" style={{ minWidth: '12rem' }} header="VILLE" ></Column>
    <Column field="role"  filterField="role" style={{ minWidth: '12rem' }} header="ROLE"></Column>
    <Column field="status"  filterField="status" style={{ minWidth: '12rem' }}  header="STATUT"></Column>
    <Column field="action"  filterField="action" style={{ minWidth: '12rem' }}  header="ACTION"></Column>


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
