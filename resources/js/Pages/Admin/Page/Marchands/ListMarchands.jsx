import { useState } from "react";
import Footer from "../../Components/Footer";
import Menu from "../../Components/Menu";
import NavBar from "../../Components/NavBar";
import Table from "../../Components/PageComponents/Table";
import Button from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';
import AddForm from "../../Components/PageComponents/AddForm";

export default function ListMarchands({merchands}) {

  const [show, setShow] = useState(false);
  const handleClose = () => setShow(false);
  const handleShow = () => setShow(true);
  const [marchandsTable, setMarchandsTable] = useState([
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
                  <h5 class="card-header">Entrez les informations d'ajout d'un marchand</h5>
                  <form class="card-body">
                    <h6>1. Détails de compte</h6>

                    <div class="row g-6">
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>

                          </select>
                          <label for="multicol-country">Utilisateurs</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="text" id="multicol-username" class="form-control" placeholder="john.doe" />
                          <label for="multicol-username">nom</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="input-group input-group-merge">
                          <div class="form-floating form-floating-outline">
                            <input type="text" id="multicol-email" class="form-control" placeholder="john.doe" aria-label="john.doe" aria-describedby="multicol-email2" />
                            <label for="multicol-email">Email</label>
                          </div>
                          <span class="input-group-text" id="multicol-email2">@example.com</span>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <select id="multicol-country" class="select2 form-select" data-allow-clear="true">
                            <option value="">Selectionner</option>
                            <option value="">Garoua</option>
                            <option value="">Yaoundé</option>


                          </select>
                          <label for="multicol-country">Ville</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-password-toggle">
                          <div class="input-group input-group-merge">
                            <div class="form-floating form-floating-outline">
                              <input type="password" id="multicol-password" class="form-control" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="multicol-password2" />
                              <label for="multicol-password">Code marchand</label>
                            </div>
                            <span class="input-group-text cursor-pointer" id="multicol-password2"><i class="ri-eye-off-line"></i></span>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-password-toggle">
                          <div class="input-group input-group-merge">
                            <div class="form-floating form-floating-outline">
                              <input type="password" id="multicol-confirm-password" class="form-control" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="multicol-confirm-password2" />
                              <label for="multicol-confirm-password">Confirm code marchand</label>
                            </div>
                            <span class="input-group-text cursor-pointer" id="multicol-confirm-password2"><i class="ri-eye-off-line"></i></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <hr class="my-6 mx-n4" />
                    <h6>2. Autres informations</h6>
                    <div class="row g-6">
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="number" id="multicol-first-name" class="form-control" placeholder="500 000" />
                          <label for="multicol-first-name">Caution</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                          <input type="file" id="multicol-last-name" class="form-control" placeholder="Doe" />
                          <label for="multicol-last-name">Contrat </label>
                        </div>
                      </div>



                    </div>

                  </form>
                </div>

              </AddForm>


              <Table
                title="Liste des marchands"
                elementsHeadTable={elementsHeadTable}
              >

                {merchands.map(e =>
                  <tr>
                    <td>  {e.ref} </td>
                    <td>  {e.user.name} </td>
                    <td>  {e.user.city} </td>
                    <td>  {e.status} </td>
                    <td>  {e.ACTION} </td>
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
