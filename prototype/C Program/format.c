/*This is the format for the auto-generated functions*/

void	rules(void)
{
	if (RULE)
		SET_X;
	...
}

int		done(void)
{
	if (REQUESTED_VARIABLE != 0 && REQUESTED_VARIABLE != 1)
		return (1);
	...
	return (0);
}

int		trues(void)
{
	VARIABLE = 2;
	...
}
