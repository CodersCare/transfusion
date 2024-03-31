..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

New to multilingual TYPO3 and translations?
===========================================

..  tip::

    Get an introduction:
    https://docs.typo3.org/m/typo3/tutorial-editors/main/en-us/Languages/Index.html

    Gte more details about frontend localization and especiall connected and free mode translations:
    https://docs.typo3.org/m/typo3/guide-frontendlocalization/main/en-us/Index.html

..  _what-it-does:

What does it do?
================

..  attention::

    **The TransFusion extension is currently a work in progress and in an early alpha state!**

    While it effectively manages disconnecting and reconnecting default CTypes of the original TYPO3 core, it currently lacks the capability to handle inline relations such as file references or container elements with children.

    **Please use this tool at your own risk.**

TransFusion is designed to assist TYPO3 editors in managing connected, free, and mixed mode translations within the language view of the page module. It offers distinct functionalities tailored to each mode, accessible via dedicated buttons.

..  figure:: /Images/TransFusionButtons.png
    :class: with-shadow
    :alt: Introduction Package
    :width: 1024px

    TransFusion buttons in the language view of the page module.

In connected mode, editors can disconnect existing connections, while in free or mixed mode, they can easily reconnect disconnected or unconnected records.

While record disconnection occurs instantly, transitioning the target language content to free mode, reconnection requires interaction. TransFusion simplifies this process with a Connector for the Page Module. This tool categorizes available target language records based on their states, ranging from confirmed to broken or orphaned, facilitating the identification of unconnected records associated with a parent record in the default language.

..  figure:: /Images/TransFusionConnector.png
    :class: with-shadow
    :alt: Introduction Package
    :width: 1024px

    TransFusion connector in the language view of the page module.
