# mollie-for-paymattic
An example plugin for Paymattic payment module integrations.

# procedure of integrating a custom payment module with paymattic
Here we tried to show how to integrate a custom payment gateway with paymattic by showcasing payment gateway integration.

## module structure
- plugin file
- settings
- Api

### sample_payment_file.php
In the main plugin file of the custom payment you need to do some mandatory checks and use the desired <b>wppayform_loaded</b> hook to trigger integrating your custome module. 

![base_file](base_file.png)

Go through the <b>mollie-for-paymattic.php</b> file to get it clear.


### settings
To make your custom payment compatible and visible in paymattic you need to provide desired settings in a specific way. Setting constructed with two defferent file
- Element.php
- Settings.php

#### Element.php
Element.php file where you make your custom payment as a paymattic component. This file basically extend the BaseComponent file of Paymattic and add the necessary hooks in the construct menthod.

</br>




