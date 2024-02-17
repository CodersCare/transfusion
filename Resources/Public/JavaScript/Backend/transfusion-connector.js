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
          if (status === 'broken') {
            targetCell = fullElement.closest('td').previousElementSibling.previousElementSibling.previousElementSibling;
          }
          if (targetCell !== null) {
            targetCell.append(fullElement);
            for (var i = 0; i < inputElements.length; i++) {
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
          if (status === 'broken') {
            targetCell = fullElement.closest('td').nextElementSibling.nextElementSibling.nextElementSibling;
          }
          if (targetCell !== null) {
            targetCell.append(fullElement);
            for (var i = 0; i < inputElements.length; i++) {
              inputElements[i].setAttribute('disabled', 'disabled');
            }
          }
        }
      }
    }

    var buttons = document.getElementsByClassName("btn-transfusion-selector");

    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener("click", moveOnceOrTwice, false);
    }
  }
}

export default new TransfusionConnectorMoveAction();
