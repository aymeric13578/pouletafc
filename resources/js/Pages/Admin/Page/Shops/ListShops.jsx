import { useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";
import Button from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';
import AddForm from "../../Components/PageComponents/AddForm";
import { Column } from 'primereact/column';

export default function ListShops({shops}) {


  const [show, setShow] = useState(false);
  const handleClose = () => setShow(false);
  const handleShow = () => setShow(true);
  const [shopsTable, setShopsTable] = useState([
    {
      MATRICULE: "aaaa",
      NOM: "alphonse",
      VILLE: "Yaoundé",
      STATUT: "Validé",
      ACTION: "",
    },
    {
      MATRICULE: "aaaa",
      NOM: "alphonse",
      VILLE: "Yaoundé",
      STATUT: "Validé",
      ACTION: "",
    },
  ]);

  const elementsHeadTable = [
    "MATRICULE",
    "NOM",
    "VILLE",
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

              <Button variant="primary" onClick={handleShow}>
                Ajouter
              </Button>

              <AddForm show={show} handleClose={handleClose}>
                <div class="card mb-6">
                  <h5 class="card-header">Entrez les informations d'ajout d'une boutique</h5>
                  <form class="card-body">
                    <h6>1. Détails de compte</h6>

                    <div class="row g-6">
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>

                          </select>
                          <label for="multicol-country">marchand</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="text" id="multicol-username" class="form-control" placeholder="glotelho" />
                          <label for="multicol-username">nom </label>
                        </div>
                      </div>



                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>

                          </select>
                          <label for="multicol-country">ville</label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <textarea type="text" cols="30" rows="30" id="multicol-username" class="form-control" />
                          <label for="multicol-username">description </label>
                        </div>
                      </div>




                    </div>
                    <hr class="my-6 mx-n4" />
                    <h6>2. Autres informations</h6>
                    <div class="row g-6">

                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="file" id="multicol-last-name" class="form-control" placeholder="Doe" />
                          <label for="multicol-last-name"> Banniere </label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="file" id="multicol-last-name" class="form-control" placeholder="Doe" />
                          <label for="multicol-last-name">  Logo</label>
                        </div>
                      </div>




                    </div>

                  </form>
                </div>

              </AddForm>
              <Table
                title="Liste des boutiques"
                elementsHeadTable={elementsHeadTable}
                elements={shops}
              >

              
                <Column field="ref" filterField="ref" style={{ minWidth: '12rem' }} header="REFERENCE" ></Column>
                <Column field="name" filterField="name" style={{ minWidth: '12rem' }} header="NOM"></Column>
                <Column field="type" filterField="type" style={{ minWidth: '12rem' }} header="TYPE"></Column>
                <Column field="city" filterField="city" style={{ minWidth: '12rem' }} header="VILLE"></Column>
                <Column field="merchand.user.name" filterField="merchand.user.name" style={{ minWidth: '12rem' }} header="MARCHAND"></Column>
                <Column field="status" filterField="status" style={{ minWidth: '12rem' }} header="STATUT"></Column>
                <Column field="action" filterField="action" style={{ minWidth: '12rem' }} header="ACTION"></Column>

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
