---
title: Survey Rejected, Some Information Not Correct
created_at:  2008-11-10 13:00:40.000000 -06:00
directory: cgi-bin/inc/templates/
extension: php
filter:
  - erb
  - textile
---

<%= render(:partial => "intro_text") %>

<div class="LV_validation_message LV_invalid">
<?php echo( $this->prepare('error_message') ); ?>
</div>

<%= render(:partial => "survey_form") %>
