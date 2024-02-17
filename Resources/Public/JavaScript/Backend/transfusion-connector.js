/**
 * Module: @t3thi/transfusion/transfusion-connector.js
 * Provide actions to move items into the confirmed column and back
 */

class TransfusionConnectorMoveAction {
  constructor() {
    var moveOnceOrTwice = function(event) {
      event.preventDefault();
      if(typeof this.dataset.direction != "undefined" && typeof this.dataset.frequency != "undefined") {
        var direction = this.dataset.direction;
        var frequency = this.dataset.frequency;
        var fullElement = this.closest('.t3-page-ce-wrapper');
        var inputElements = fullElement.getElementsByTagName('input');
        var targetCell = null;
        if (direction === 'left') {
          if (frequency === '1') {
            targetCell = fullElement.closest('td').previousElementSibling;
          }
          if (frequency === '2') {
            targetCell = fullElement.closest('td').previousElementSibling.previousElementSibling;
          }
          if (targetCell !== null) {
            targetCell.append(fullElement);
            for (var i = 0; i < inputElements.length; i++) {
              inputElements[i].removeAttribute('disabled');
            }
          }
        }
        if (direction === 'right') {
          if (frequency === '1') {
            targetCell = fullElement.closest('td').nextElementSibling;
          }
          if (frequency === '2') {
            targetCell = fullElement.closest('td').nextElementSibling.nextElementSibling;
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
