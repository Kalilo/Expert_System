/*This is the format for the auto-generated functions*/

void	rules(void)
{
	if (RULE)
		SET_X;
	...
}

char	done(void)
{
	if (REQUESTED_VARIABLE != 0 && REQUESTED_VARIABLE != 1)
		return (1);
	...
	return (0);
}

void	trues(void)
{
	VARIABLE = 3;
	...
}

void	display(void)
{
	if (REQUESTED_VARIABLE)
		write(1, "REQUESTED_VARIABLE is true.\n", 11);
	else
		write(1, "REQUESTED_VARIABLE is false.\n", 12);
	...
}

int		main()
{
	int		count;

	count = 0;
	trues();
	while (count < NUM_RULES && done())
	{
		rules();
		count++;
	}
	display();
}
