-- Compra mínima: 1 número (precios por cantidad: 1 / 2 / 3+).
UPDATE raffles
SET min_quantity_raffle = 1
WHERE min_quantity_raffle > 1;
