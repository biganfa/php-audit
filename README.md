# PhpAudit

<table>
<thead>
<tr>
<th>Social</th>
<th>Legal</th>
<th>Release</th>
<th>Tests</th>
<th>Code</th>
</tr>
</thead>
<tbody>
<tr>
<td>
<a href="https://gitter.im/SetBased/php-audit?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge"><img src="https://badges.gitter.im/SetBased/php-audit.svg" alt="Gitter"/></a>
</td>
<td>
<a href="https://packagist.org/packages/setbased/php-audit"><img src="https://poser.pugx.org/setbased/php-audit/license" alt="License"/></a>
</td>
<td>
<a href="https://packagist.org/packages/setbased/php-audit"><img src="https://poser.pugx.org/setbased/php-audit/v/stable" alt="Latest Stable Version"/></a><br/>
<a href="https://www.versioneye.com/user/projects/56e2e5a3df573d00472cd2ad"><img src="https://www.versioneye.com/user/projects/56e2e5a3df573d00472cd2ad/badge.svg?style=flat" alt="Dependency Status"/></a>
</td>
<td>
<a href="https://travis-ci.org/SetBased/php-audit"><img src="https://travis-ci.org/SetBased/php-audit.svg?branch=master" alt="Build Status"/></a><br/>
<a href="https://scrutinizer-ci.com/g/SetBased/php-audit/?branch=master"><img src="https://scrutinizer-ci.com/g/SetBased/php-audit/badges/coverage.png?b=master" alt="Code Coverage"/></a>
</td>
<td>
<a href="https://scrutinizer-ci.com/g/SetBased/php-audit/?branch=master"><img src="https://scrutinizer-ci.com/g/SetBased/php-audit/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality"/></a><br/>
<a https://www.codacy.com/app/p-r-water/php-audit"><img src="https://api.codacy.com/project/badge/grade/c0510d398830476689de3bb52ec2daff"/></a><br/>
<a href="https://travis-ci.org/SetBased/php-audit"><img src="http://php7ready.timesplinter.ch/SetBased/php-audit/badge.svg" alt="PHP 7 ready"/></a>
</td>
</tr>
</tbody>
</table>


PhpAudit is a tool for creating and maintaining audit tables and triggers for creating audit trails of data changes in MySQL databases.


# Features

PhpAudit has the following features:
* Creates audit tables for tables in your database for which auditing is required.
* Creates triggers on tables for recording inserts, updates, and deletes of rows.
* Helps you to maintain audit tables and trigger when you modify your application's tables.
* Reports differences in table structure between your application's tables and audit tables.
* Disabling triggers under certain conditions.
* Flexible configuration. You can define additional columns to audit tables. For example to log user and session IDs.

Using the audit trail you track changes made to the data of your application by the users of the application. 
Even of data that has been deleted or changed back to its original state. Also, you can track how your application manipulates data and find bugs if your application.
 


# Real world example

In this section we give a real world example taken from a tournament on the [Nahouw](https://www.nahouw.net/page/trn_all/).
We have reduced the tournament table to two columns and changed some IDs for simplification.
```SQL
select * 
from   nahouw.NAH_TOURNAMENT
where  trn_id = 4473
```

| trn_id | trn_name      |
| ------ | ------------- |
| 4773   | Correct name  |

The audit trail for this tournament:
```SQL
select * 
from   nahouw_audit.NAH_TOURNAMENT
where  trn_id = 4473
```

| audit_timestamp     | audit_statement | audit_state | audit_uuid         | audit_rownum | audit_ses_id | audit_usr_id | trn_id | trn_name      |
| ------------------- | --------------- | ----------- | ------------------ | ------------ | ------------ |------------- | ------ | ------------- |
| 2012-05-05&#160;08:36:06 | INSERT          | NEW         | 310616503508533789 | 2            | 34532889     | 65           | 4773   | Wrong&#160;name    |
| 2013-02-01&#160;10:55:01 | UPDATE          | OLD         | 311037142136521378 | 5            | 564977477    | 107          | 4773   | Wrong&#160;name    |  
| 2013-02-01&#160;10:55:01 | UPDATE          | NEW         | 311037142136521378 | 5            | 564977477    | 107          | 4773   | Correct&#160;name  |


Notice that the audit table has 7 additional columns. You can configure more or less columns and name them to your needs.
| Column          | Remark                               |
| --------------- | ------------------------------------ |
| audit_timestamp | The time the statement was executed. |
| audit_statement | The type of statement. One of INSERT, UPDATE, OR DELETE. |

* audit_timestamp The time the statement was executed.
* audit_statement The type of statement. One of INSERT, UPDATE, OR DELETE.
* audit_sate The state of the row. NEW or OLD. 
* audit_uuid A UUID per database connection. Using this ID we can track all changes made during a page request.
* audit_rownum The number of the audit row within the UUID. Using this column we can track the order in which changes are made during a page request.
* audit_ses_id The ID the session of the web application.
* audit_usr_id The ID of the user has made the page request.     

From the audit trail we can see that user 65 has initially entered the tournament with a wrong name. 
We see that the tournament insert statement was the second statement executed. Using UUID 310616503508533789 we found the first statement was an insert statement of the tournament's location which is stored in another table. 
Later user 107 has changed the tournament name to its correct name.

On table NAH_TOURNAMENT we have three triggers, one for insert statements, one for update statements, and one for delete statements.
Below is the code for the update statement (the code for the other triggers look similar).
```SQL
create trigger `nahouw`.`trg_trn_update`
after UPDATE on `nahouw`.`NAH_TOURNAMENT`
for each row
begin
  if (@audit_uuid is null) then
    set @audit_uuid = uuid_short();
  end if;
  set @audit_rownum = ifnull(@audit_rownum, 0) + 1;
  insert into `nahouw_audit`.`NAH_TOURNAMENT`(audit_timestamp,audit_type,audit_state,audit_uuid,rownum,audit_ses_id,audit_usr_id,trn_id,trn_name)
  values(now(),'UPDATE','OLD',@audit_uuid,@audit_rownum,@abc_g_ses_id,@abc_g_usr_id,OLD.`trn_id`,OLD.`trn_name_id`); 
  insert into `nahouw_audit`.`NAH_TOURNAMENT`(audit_timestamp,audit_type,audit_state,audit_uuid,rownum,audit_ses_id,audit_usr_id,,trn_id,trn_name)
  values(now(),'UPDATE','NEW',@audit_uuid,@audit_rownum,@abc_g_ses_id,@abc_g_usr_id,NEW.`trn_id`,NEW.`trn_name`);
end
```


# Installation 

PhpAudit can be installed using composer:
```sh
composer require setbased/php-audit
```

Or you can obtain the sources at [GitHub](https://github.com/SetBased/php-audit).


# Manual

Right now we are working on the manual and will be online soon.


# Contributing

We are looking for contributors. We can use your help for:
*	Fixing bugs and solving issues.
*	Writing documentation.
*	Developing new features.
*	Code review.
*	Implementing PhpAudit for other database systems.

You can contribute to this project in many ways:
*	Fork this project on [GitHub](https://github.com/SetBased/php-audit) and create a pull request.
*	Create an [issue](https://github.com/SetBased/php-audit/issues/new) on GitHub.
*	Asking critical questions.
*	Contacting us at [Gitter](https://gitter.im/SetBased/php-audit).


# Support
  
If you are having issues, please let us know. Contact us at [Gitter](https://gitter.im/SetBased/php-audit) or create an issue on [GitHub](https://github.com/SetBased/php-audit/issues/new).

For commercial support, please contact us at info@setbased.nl.


# Limitations

PhpAudit has the following limitations:
* A `TRUNCATE TABLE` will remove all rows from a table and does not execute triggers. Hence, the removing of those rows will not be logged in the audit table.
* A delete or update of a child row caused by a cascaded foreign key action of a parent row will not activate triggers on the child table. Hence, the update or deletion of those rows will not be logged in the audit table.

Both limitations arise from the behavior of MySQL. In practice these limitations aren't of any concern. In applications where tables are "cleaned" with a `TRUNCATE TABLE` we never had the need to audit these tables. We found the same for child tables with a `ON UPDATE CASCADE` or `ON UPDATE SET NULL` reference option.  


#  License
  
The project is licensed under the MIT license.
 
