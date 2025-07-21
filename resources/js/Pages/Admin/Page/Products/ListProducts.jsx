import { useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";
import Button from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';
import AddForm from "../../Components/PageComponents/AddForm";
import { Column } from 'primereact/column';


export default function ListProducts({products}) {

  const [show, setShow] = useState(false);
  const handleClose = () => setShow(false);
  const handleShow = () => setShow(true);
  const [productsTable, setProductsTable] = useState([
    {
      CODE: "aaaa",
      DESIGNATION: "alphonse",
      PRIX: 3000,
      QUANTITE: 5,
      CATEGORIE: "alphonse",
      SOUS_CATEGORIE: "alphonse",
      STATUS: "validé",
      ACTIONS: ""
    },
    {
      CODE: "aaaa",
      DESIGNATION: "alphonse",
      PRIX: 3000,
      QUANTITE: 5,
      CATEGORIE: "alphonse",
      SOUS_CATEGORIE: "alphonse",
      STATUS: "validé",
      ACTIONS: ""
    },
  ]);

  const elementsHeadTable = [
    "CODE",
    "DESIGNATION",
    "PRIX",
    "STOCK_INITIAL",
    "STOCK",
    "CATEGORIE",
    "SOUS_CATEGORIE",
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

              <Button variant="primary" onClick={handleShow}>
                Ajouter
              </Button>

              <AddForm show={show} handleClose={handleClose}>
                <div class="card mb-6">
                  <h5 class="card-header">Entrez les informations d'ajout d'un produit</h5>
                  <form class="card-body">
                    <h6>1. Détails de compte</h6>

                    <div class="row g-6">
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>

                          </select>
                          <label for="multicol-country">Boutique</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="text" id="multicol-username" class="form-control" placeholder="poulet cru" />
                          <label for="multicol-username">Désignation </label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="number" id="multicol-username" class="form-control" placeholder="500" />
                          <label for="multicol-username">Prix unitaire(FCFA) </label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="text" id="multicol-username" class="form-control" placeholder="xxxxxx" />
                          <label for="multicol-username">Code du produit </label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>

                          </select>
                          <label for="multicol-country">Catégorie</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" readonly class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>

                          </select>
                          <label for="multicol-country">Sous-Catégorie</label>
                        </div>
                      </div>
                      <div class="col-md-12">
                        <div class="form-floating form-floating-outline">
                          <textarea type="text" cols="30" rows="30" id="multicol-username" class="form-control" /> 
                        <label for="multicol-username">description </label>
                      </div>
                    </div>




                </div>
                <hr class="my-6 mx-n4" />
                <h6>2. Autres informations</h6>
                <div class="row g-6">

                  <div class="col-md-6 ">
                    <div class="form-floating form-floating-outline">
                      <input type="file" id="multicol-last-name" class="form-control" placeholder="Doe" />
                      <label for="multicol-last-name">  Image 1 </label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                      <input type="file" id="multicol-last-name" class="form-control" placeholder="Doe" />
                      <label for="multicol-last-name">  Image 2 </label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                      <input type="file" id="multicol-last-name" class="form-control" placeholder="Doe" />
                      <label for="multicol-last-name">  Image 3</label>
                    </div>
                  </div>



                </div>
                
              </form>
            </div>


          </AddForm>
          <Table
            title="Liste des produits"
            elementsHeadTable={elementsHeadTable}
            elements = {products}
          >

          

    <Column field="ref" filterField="ref"  style={{ minWidth: '12rem' }} header="REFERENCE" ></Column>
    <Column field="name"  filterField="name" style={{ minWidth: '12rem' }}  header="DESIGNATION"></Column>
    <Column field="price"  filterField="price" style={{ minWidth: '12rem' }} header="PRIX" ></Column>
    <Column field="stock_init"  filterField="stock_init" style={{ minWidth: '12rem' }} header="STOCK INITIAL" ></Column>
    <Column field=""  filterField="" style={{ minWidth: '12rem' }} header="STOCK" ></Column>
    <Column field="category.name"  filterField="category.name" style={{ minWidth: '12rem' }} header="CATEGORIE"></Column>
    <Column field="sub_category.name"  filterField="sub_category.name" style={{ minWidth: '12rem' }} header="SOUS CATEGORIE"></Column>

    <Column field="status"  filterField="status" style={{ minWidth: '12rem' }}  header="STATUT"></Column>
    <Column field="action"  filterField="action" style={{ minWidth: '12rem' }}  header="ACTION"></Column>

          </Table>

          {/* <hr class="my-12" /> */}




        </div>

        <Footer />

        <div class="content-backdrop fade"></div>
      </div>
    </div>
      </div >

      <div className="layout-overlay layout-menu-toggle"></div>

      <div className="drag-target"></div>
    </div >
  );
}
