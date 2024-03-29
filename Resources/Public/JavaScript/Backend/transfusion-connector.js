/**
 * Module: @t3thi/transfusion/transfusion-connector.js
 * Provide actions to move items into the confirmed column and back
 */

class TransfusionConnectorActions {
  constructor() {
    var enableInputElements = function(fullElement, action){
      var inputElements = fullElement.getElementsByTagName('input');
      for (var i = 0; i < inputElements.length; i++) {
        if (!inputElements[i].classList.contains(action)) {
          continue;
        }
        inputElements[i].removeAttribute('disabled');
      }
    }
    var activateButton = function(button, action) {
      button.setAttribute('title', button.dataset.enabledtitle);
      button.classList.remove('btn-default');
      button.classList.add('btn-warning');
      var fullElement = button.closest('.t3-page-ce-wrapper');
      enableInputElements(fullElement, action)
    }
    var disableInputElements = function(fullElement, action){
      var inputElements = fullElement.getElementsByTagName('input');
      for (var i = 0; i < inputElements.length; i++) {
        if (!inputElements[i].classList.contains(action)) {
          continue;
        }
        inputElements[i].setAttribute('disabled', 'disabled');
      }
    }
    var deactivateButton = function(button, action) {
      button.setAttribute('title', button.dataset.disabledtitle);
      button.classList.add('btn-default');
      button.classList.remove('btn-warning');
      var fullElement = button.closest('.t3-page-ce-wrapper');
      disableInputElements(fullElement, action)
    }
    var moveOnceOrTwice = function(event) {
      event.preventDefault();
      if(typeof this.dataset.direction != "undefined" && typeof this.dataset.status != "undefined") {
        var direction = this.dataset.direction;
        var status = this.dataset.status;
        var action = this.dataset.action;
        var fullElement = this.closest('.t3-page-ce-wrapper');
        var deleteButton = fullElement.getElementsByClassName('btn-transfusion-delete')[0];
        var detachButton = fullElement.getElementsByClassName('btn-transfusion-detach')[0];
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
              enableInputElements(fullElement, 'change')
              deactivateButton(detachButton, 'btn-transfusion-detach');
              disableInputElements(fullElement, 'detach');
              deactivateButton(deleteButton, 'btn-transfusion-delete');
              disableInputElements(fullElement, 'delete');
            }
          }
          if (parentCell !== null) {
            if (parentCell.getElementsByClassName('t3-page-ce-wrapper').length) {
              alert('You can only create one new parent element for each translated record!');
            } else {
              var parentElement = fullElement.cloneNode(true);
              parentCell.append(parentElement);
              enableInputElements(parentElement, 'new')
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
            disableInputElements(fullElement, 'change');
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
      var detachButton = fullElement.getElementsByClassName('btn-transfusion-detach')[0];
      if (deleteButton.getAttribute('disabled')==='disabled') {
        activateButton(this, 'delete');
        deactivateButton(detachButton, 'detach');
        disableInputElements(fullElement, 'change');
      } else {
        deactivateButton(this, 'delete');
        if (fullElement.closest('.transfusion-confirmed')
          && fullElement.getElementsByClassName('btn-transfusion-selector').length) {
          enableInputElements(fullElement, 'change');
        }
      }
    }

    var deleteButtons = document.getElementsByClassName("btn-transfusion-delete");

    for (var i = 0; i < deleteButtons.length; i++) {
      deleteButtons[i].addEventListener("click", markForDeletion, false);
    }

    var detachAllConnections = function(event) {
      event.preventDefault();
      var fullElement = this.closest('.t3-page-ce-wrapper');
      var detachButton = fullElement.getElementsByClassName('detach')[0];
      var deleteButton = fullElement.getElementsByClassName('btn-transfusion-delete')[0];
      if (detachButton.getAttribute('disabled')==='disabled') {
        activateButton(this, 'detach');
        deactivateButton(deleteButton, 'delete');
        disableInputElements(fullElement, 'change');
      } else {
        deactivateButton(this, 'detach');
        if (fullElement.closest('.transfusion-confirmed')
          && fullElement.getElementsByClassName('btn-transfusion-selector').length) {
          enableInputElements(fullElement, 'change');
        }
      }
    }

    var detachButtons = document.getElementsByClassName("btn-transfusion-detach");

    for (var i = 0; i < detachButtons.length; i++) {
      detachButtons[i].addEventListener("click", detachAllConnections, false);
    }

    var checkMarkedForRemovalOrDeletion = function(event) {
      event.preventDefault();
      var markedForRemoval = this.getElementsByClassName('btn-transfusion-detach btn-warning');
      var markedForDeletion = this.getElementsByClassName('btn-transfusion-delete btn-warning');
      if (markedForRemoval.length && markedForDeletion.length) {
        if (confirm('Are you sure you want to detach connections from or delete marked records?')) {
          this.submit();
        }
      } else if (markedForRemoval.length) {
        if (confirm('Are you sure you want to detach connections from marked records?')) {
          this.submit();
        }
      } else if (markedForDeletion.length) {
        if (confirm('Are you sure you want to delete marked records?')) {
          this.submit();
        }
      } else {
        this.submit();
      }
    }

    document.getElementById("TransfusionController").addEventListener('submit', checkMarkedForRemovalOrDeletion, false);

  }
}

export default new TransfusionConnectorActions();
