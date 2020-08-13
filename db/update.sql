-- PRE PRE PRE doctrine-update =============================================================

-- POST POST POST doctrine-update ===========================================================
-- competitors 25952
update competitors c join associations a on a.id = c.associationid join leagues l on l.associationid = a.id join competitions comp on comp.leagueid = l.id join tournaments t on t.competitionid = comp.id
set c.tournamentId = t.id
where t.updated = true;

CREATE INDEX CDKTMP ON places(competitorid);

-- 23565
update places p join competitors c on c.id = p.competitorid join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundnumbers rn on rn.id = r.numberid and rn.number = 1 join tournaments t on t.competitionId = rn.competitionId
set c.placeNr = p.number, c.pouleNr = po.number
where	t.updated = true;

-- qualified places
update 	places p join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundnumbers rn on rn.id = r.numberid and rn.number > 1 join competitions c on c.id = rn.competitionid join tournaments t on t.competitionid = c.id
set 		p.qualifiedPlaceId = (
    select 	pprev.id
    from 	places pprev join poules poprev on poprev.id = pprev.pouleid join rounds rprev on rprev.id = poprev.roundid join roundnumbers rnprev on rnprev.id = rprev.numberid
    where 	rnprev.number = rn.number-1 and pprev.competitorid = p.competitorid
)
where	t.updated = true;

ALTER TABLE places DROP INDEX CDKTMP;

-- php bin/console.php app:create-default-planning-input --placesRange=2-4 --sendCreatePlanningMessage=true

-- CUSTOM IMPORT =============================
-- mysqldump -u fctoernooi_a_dba -p fctoernooiacc planninginputs plannings planningsports planningfields planningpoules planningplaces planningreferees planninggames planninggameplaces > planninginputs.sql
-- mysql -u fctoernooi_dba -p fctoernooi < planninginputs.sql
