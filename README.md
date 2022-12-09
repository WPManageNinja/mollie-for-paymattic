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


### settings directory
To make your custom payment compatible and visible in paymattic you need to provide desired settings in a specific way. Setting constructed with two defferent file
- Element.php
- Settings.php

#### Element file
Element.php file where you make your custom payment as a paymattic component. This file basically extend the BaseComponent file of Paymattic and add the 
necessary hooks in the construct menthod.

</br>

![construct_of_element_file](construct_of_element_file.png)

Go through the <b>settings/MollieElement.php</b> and do exactly by replacing your custom payment name.

#### settings file
settings.php file where you give all necessary global fileds to save the payments credentials: live/test public/secret keys to make your payment module actually works. This file need to extend the basepayment class of paymattic. implements all the function by replacing yours.

In this file where you need to be little bit more careful is the <b>globalFields</b> method.

</br>

![globalFields](global_fields.png)

There are four, two(in some case, as here in mollie) credential fields <b>type</b>.
- test_secret_key
- test_pub_key
- live_secret_key
- live_pub_key

Field type must be exact same. [ex: if you need test api key/test secret key] in both case you should provide field type as test_secret_key. But you can keep field keep as you want.

But field key of credential fields you can give as your payment module requires. ex: for mollie you need <b>test_api_key</b> and <b>live_api_key</b>.

Make sure you have same key in (get/validate api) methods as you set keys on global fields.

![get_api_keys](globalFields.png)

rest, implement all the methods as given on <b>settings/settings.php</b> and replace with your necessary settings but keep the format same.

### API directory
To process payments you needs api interaction

