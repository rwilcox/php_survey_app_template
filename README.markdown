Into (2015)
===========================

I've played with PHP a bit, here's a project I did with it.

The idea was to quickly create survey apps for clients - to allow end users to answer customized questionaries and have their results save to a CSV for later processing.

So, SurveyMonkey slightly before SurveyMonkey.

This uses PHP for the form logic, but the static stuff is created via Webby, a Ruby static site tool. I've done this trick in the past: using a static site tool to wrap more dynamic content.


Intro (2011)
===========================

This repository is meant to be cloned when a new survey app is required, and patches pulled from this when a survey app needs updates.

This survey application is a [Webby](http://webby.rubyforge.org/) application that spits out an initial HTML landing page, and two PHP templates used by the save\_email.php page depending on the results of the save.


Notes for cloner-s
==========================

You need to change the following files:

  * layouts/default.txt --> the generic layout for your site.
  * templates/\_survey_form.erb --> the survey fields etc to fill out. These will be propagated to the normal survey form and the error form.
  * templates/\_intro_text.erb --> introductory text to your survey. These will be propagated to the normal survey form and the error form.
  * content/javascripts/validations.js --> set up the validations for your fields
  * cgi-bin/save\_email.php --> Customize certain behavior here. This has ERB in it which will populate saving code based on items you created with input_item, so you just need to add your own special behaviors (there should be none)

Deploying
=========================

(fill me in)
