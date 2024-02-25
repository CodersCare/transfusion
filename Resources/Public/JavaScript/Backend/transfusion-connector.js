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
        var action = this.dataset.action;
        var fullElement = this.closest('.t3-page-ce-wrapper');
        var inputElements = fullElement.getElementsByTagName('input');
        var parentCell = null;
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
          if (status === 'orphaned' && action === 'new') {
            parentCell = fullElement.closest('td').previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling;
          }
          if (targetCell !== null) {
            if (targetCell.getElementsByClassName('t3-page-ce-wrapper').length) {
              alert('You can only connect one target element to each original record!');
            } else {
              targetCell.append(fullElement);
              for (var i = 0; i < inputElements.length; i++) {
                if (!inputElements[i].classList.contains('change')) {
                  continue;
                }
                inputElements[i].removeAttribute('disabled');
              }
            }
          }
          if (parentCell !== null) {
            if (parentCell.getElementsByClassName('t3-page-ce-wrapper').length) {
              alert('You can only create one new parent element for each translated record!');
            } else {
              var parentElement = fullElement.cloneNode(true);
              var parentInputElements = parentElement.getElementsByTagName('input');
              parentCell.append(parentElement);
              for (var i = 0; i < parentInputElements.length; i++) {
                if (!parentInputElements[i].classList.contains('new')) {
                  continue;
                }
                parentInputElements[i].removeAttribute('disabled');
              }
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
            if (status === 'orphaned') {
              fullElement.closest('tr').getElementsByClassName('transfusion-original')[0].getElementsByClassName('t3-page-ce-wrapper')[0].remove();
            }
            for (var i = 0; i < inputElements.length; i++) {
              if (!inputElements[i].classList.contains('change')) {
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
        this.setAttribute('title', this.dataset.enabledtitle);
        this.classList.remove('btn-default');
        this.classList.add('btn-warning');
        alert('Marked for deletion');
      } else {
        deleteButton.setAttribute('disabled', 'disabled');
        this.setAttribute('title', this.dataset.disabledtitle);
        this.classList.add('btn-default');
        this.classList.remove('btn-warning');
      }
    }

    var deleteButtons = document.getElementsByClassName("btn-transfusion-delete");

    for (var i = 0; i < deleteButtons.length; i++) {
      deleteButtons[i].addEventListener("click", markForDeletion, false);
    }

    var removeAllConnections = function(event) {
      event.preventDefault();
      var fullElement = this.closest('.t3-page-ce-wrapper');
      var inputElements = fullElement.getElementsByClassName('remove');
      if (inputElements[0].getAttribute('disabled')==='disabled') {
        for (var i = 0; i < inputElements.length; i++) {
          if (!inputElements[i].classList.contains('remove')) {
            continue;
          }
          inputElements[i].removeAttribute('disabled');
        }
        this.classList.remove('btn-default');
        this.classList.add('btn-warning');
        alert('Marked for removal of all connections');
      } else {
        for (var i = 0; i < inputElements.length; i++) {
          if (!inputElements[i].classList.contains('remove')) {
            continue;
          }
          inputElements[i].setAttribute('disabled', 'disabled');
        }
        this.classList.remove('btn-warning');
        this.classList.add('btn-default');
      }
    }

    var removeButtons = document.getElementsByClassName("btn-transfusion-remove");

    for (var i = 0; i < removeButtons.length; i++) {
      removeButtons[i].addEventListener("click", removeAllConnections, false);
    }

    var checkMarkedForRemovalOrDeletion = function(event) {
      event.preventDefault();
      var markedForRemovalOrDeletion = this.getElementsByClassName('btn-warning');
      if (markedForRemovalOrDeletion.length) {
        if (confirm('Are you sure you want to remove or delete the marked records?')) {
          this.submit();
        }
      } else {
        this.submit();
      }
    }

    document.getElementById("TransfusionController").addEventListener('submit', checkMarkedForRemovalOrDeletion, false);

  }
}

export default new TransfusionConnectorMoveAction();
