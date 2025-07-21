import { useState } from "react";
import Button from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';



export default function AddForm({ show, handleClose,children }) {
    return (
    <Modal show={show} onHide={handleClose}>
        <Modal.Header closeButton>
            <Modal.Title>Modal heading</Modal.Title>
        </Modal.Header>
        <Modal.Body>


            {children}



        </Modal.Body>
        <Modal.Footer>
            <Button variant="secondary" onClick={handleClose}>
                Fermer
            </Button>
            <Button variant="primary" onClick={handleClose}>
                Valider
            </Button>
        </Modal.Footer>
    </Modal>


    )
}