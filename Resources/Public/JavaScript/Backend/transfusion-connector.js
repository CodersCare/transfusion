/**
 * Module: @t3thi/transfusion/transfusion-connector.js
 * Provide actions to move items into the confirmed column and back
 */

class TransfusionConnectorMoveAction {
  constructor() {
    var moveOnceOrTwice = function(event) {
      event.preventDefault();
      if(typeof this.dataset.direction != "undefined" && typeof this.dataset.status != "undefined") {
        var direction = this.dataset.direction;
        var status = this.dataset.status;
        var fullElement = this.closest('.t3-page-ce-wrapper');
        var inputElements = fullElement.getElementsByTagName('input');
        var targetCell = null;
        if (direction === 'left') {
          if (status === 'obvious') {
            targetCell = fullElement.closest('td').previousElementSibling;
          }
          if (status === 'possible') {
            targetCell = fullElement.closest('td').previousElementSibling.previousElementSibling;
          }
          if (status === 'broken' || status === 'orphaned') {
            targetCell = fullElement.closest('td').previousElementSibling.previousElementSibling.previousElementSibling;
          }
          if (targetCell !== null) {
            targetCell.append(fullElement);
            for (var i = 0; i < inputElements.length; i++) {
              if (inputElements[i].classList.contains('delete')) {
                continue;
              }
              inputElements[i].removeAttribute('disabled');
            }
          }
        }
        if (direction === 'right') {
          if (status === 'obvious') {
            targetCell = fullElement.closest('td').nextElementSibling;
          }
          if (status === 'possible') {
            targetCell = fullElement.closest('td').nextElementSibling.nextElementSibling;
          }
          if (status === 'broken' || status === 'orphaned') {
            targetCell = fullElement.closest('td').nextElementSibling.nextElementSibling.nextElementSibling;
          }
          if (targetCell !== null) {
            targetCell.append(fullElement);
            for (var i = 0; i < inputElements.length; i++) {
              if (inputElements[i].classList.contains('delete')) {
                continue;
              }
              inputElements[i].setAttribute('disabled', 'disabled');
            }
          }
        }
      }
    }

    var moveButtons = document.getElementsByClassName("btn-transfusion-selector");

    for (var i = 0; i < moveButtons.length; i++) {
      moveButtons[i].addEventListener("click", moveOnceOrTwice, false);
    }
    var markForDeletion = function(event) {
      event.preventDefault();
      var fullElement = this.closest('.t3-page-ce-wrapper');
      var deleteButton = fullElement.getElementsByClassName('delete')[0];
      if (deleteButton.getAttribute('disabled')==='disabled') {
        deleteButton.removeAttribute('disabled');
        this.classList.remove('btn-default');
        this.classList.add('btn-warning');
      } else {
        deleteButton.setAttribute('disabled', 'disabled');
        this.classList.add('btn-default');
        this.classList.remove('btn-warning');
      }
    }

    var deleteButtons = document.getElementsByClassName("btn-transfusion-delete");

    for (var i = 0; i < deleteButtons.length; i++) {
      deleteButtons[i].addEventListener("click", markForDeletion, false);
    }
  }
}

export default new TransfusionConnectorMoveAction();
